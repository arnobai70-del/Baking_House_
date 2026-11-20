<?php

namespace App\Services\Quotations;

use App\Enums\QuotationStatus;
use App\Models\Organization;
use App\Models\ProductVariant;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Crm\CrmService;
use App\Services\Notifications\OrderNotifier;
use App\Support\QuantityInput;
use App\Support\Taka;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use RuntimeException;

/**
 * The RFQ workflow. Every state change is a conditional UPDATE on the current status (so two
 * clicks or two staff members can never both win) and is written to the audit log.
 *
 *   request (anyone)  →  issue (staff, business organizations only)  →  accept / decline (owner or
 *   buyer of that organization)  →  ordered (OrderPlacer, exactly once)
 *
 * Public requests without an organization are sales leads: staff follow up offline and close them.
 */
class QuotationService
{
    public const DEFAULT_VALID_DAYS = 14;

    public function __construct(private AuditLogger $audit, private OrderNotifier $notifier, private CrmService $crm) {}

    /**
     * @param  array{contact_name: string, contact_phone: string, contact_email: ?string, request_text: string, customer_note: ?string}  $data
     */
    public function request(array $data, ?User $user, ?Organization $organization): Quotation
    {
        $quotation = new Quotation;
        $quotation->forceFill($data + [
            'number' => $this->newNumber(),
            'organization_id' => $organization?->id,
            'requested_by' => $user?->id,
            'status' => QuotationStatus::Requested,
        ])->save();

        $this->audit->record('quotation.requested', $quotation, after: [
            'number' => $quotation->number, 'organization_id' => $organization?->id, 'lead' => $organization === null,
        ], actorId: $user?->id);

        $this->crm->captureRfq($quotation);

        $this->notifier->direct('quotation_requested', $quotation->contact_phone, $quotation->contact_email,
            'Baking House quotation request '.$quotation->number,
            "Baking House: we received your quotation request {$quotation->number}. Our team will reply soon.");

        return $quotation;
    }

    /**
     * Turn raw staff rows into validated quote lines.
     *
     * @param  list<array{sku?: ?string, description?: ?string, quantity?: ?string, price?: ?string}>  $rows
     * @return list<array{variant: ?ProductVariant, sku: ?string, description: string, quantity: int, quantity_label: string, line_paisa: int}>
     *
     * @throws ValidationException
     */
    public function parseLines(array $rows): array
    {
        $lines = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $sku = strtoupper(trim((string) ($row['sku'] ?? '')));
            $description = trim((string) ($row['description'] ?? ''));
            $quantityInput = trim((string) ($row['quantity'] ?? ''));
            $priceInput = trim((string) ($row['price'] ?? ''));

            if ($sku === '' && $description === '' && $quantityInput === '' && $priceInput === '') {
                continue;
            }

            $variant = null;
            if ($sku !== '') {
                $variant = ProductVariant::query()->where('sku', $sku)->with('product:id,name')->first();
                if ($variant === null) {
                    $errors["lines.{$index}.sku"] = __('Unknown SKU :sku.', ['sku' => $sku]);

                    continue;
                }
            } elseif ($description === '') {
                $errors["lines.{$index}.description"] = __('Give a SKU or a description.');

                continue;
            }

            try {
                $quantity = QuantityInput::parse($variant, $quantityInput);
            } catch (InvalidArgumentException $e) {
                $errors["lines.{$index}.quantity"] = $e->getMessage();

                continue;
            }

            $linePaisa = Taka::parse($priceInput);
            if ($linePaisa === null || $linePaisa < 1) {
                $errors["lines.{$index}.price"] = __('Enter the line price in taka.');

                continue;
            }

            $lines[] = [
                'variant' => $variant,
                'sku' => $variant?->sku,
                'description' => mb_substr($description !== '' ? $description : (string) $variant?->product?->name, 0, 255),
                'quantity' => $quantity,
                'quantity_label' => QuantityInput::label($variant, $quantity),
                'line_paisa' => $linePaisa,
            ];
        }

        if ($errors === [] && $lines === []) {
            $errors['lines'] = __('Add at least one quoted line.');
        }
        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $lines;
    }

    /**
     * Issue (or revise) the quote. Only for business organizations; a revision is possible
     * until the buyer accepts.
     *
     * @param  list<array{variant: ?ProductVariant, sku: ?string, description: string, quantity: int, quantity_label: string, line_paisa: int}>  $lines
     *
     * @throws ValidationException
     */
    public function issue(Quotation $quotation, array $lines, CarbonImmutable $validUntil, ?string $terms, ?string $internalNote, User $staff): Quotation
    {
        if ($quotation->organization_id === null) {
            throw ValidationException::withMessages(['status' => __('This request has no business account. Follow up with the customer directly, then close it.')]);
        }
        if (! in_array($quotation->status, [QuotationStatus::Requested, QuotationStatus::Quoted], true)) {
            throw ValidationException::withMessages(['status' => __('A quotation that is :status cannot be changed.', ['status' => __($quotation->status->label())])]);
        }
        if (! $validUntil->isFuture()) {
            throw ValidationException::withMessages(['valid_until' => __('The validity date must be in the future.')]);
        }

        $before = ['status' => $quotation->status->value, 'total_paisa' => $quotation->total_paisa];
        $total = array_sum(array_column($lines, 'line_paisa'));

        DB::transaction(function () use ($quotation, $lines, $validUntil, $terms, $internalNote, $staff, $before, $total): void {
            $updated = Quotation::query()->whereKey($quotation->id)->where('status', $before['status'])->update([
                'status' => QuotationStatus::Quoted->value,
                'total_paisa' => $total,
                'valid_until' => $validUntil,
                'terms' => $terms,
                'internal_note' => $internalNote,
                'quoted_at' => now(),
                'quoted_by' => $staff->id,
                'updated_at' => now(),
            ]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['status' => __('This quotation was changed by someone else. Reload and try again.')]);
            }

            $quotation->items()->delete();
            foreach ($lines as $line) {
                $quotation->items()->forceCreate([
                    'product_variant_id' => $line['variant']?->id,
                    'sku' => $line['sku'],
                    'description' => $line['description'],
                    'quantity' => $line['quantity'],
                    'quantity_label' => $line['quantity_label'],
                    'line_paisa' => $line['line_paisa'],
                ]);
            }
        });

        $quotation->refresh();
        $this->audit->record($before['status'] === QuotationStatus::Quoted->value ? 'quotation.revised' : 'quotation.issued', $quotation, $before, [
            'status' => QuotationStatus::Quoted->value, 'total_paisa' => $total, 'lines' => count($lines), 'valid_until' => $validUntil->toDateString(),
        ], actorId: $staff->id);

        $this->crm->quotationChanged($quotation, 'issued');

        $this->notifier->direct('quotation_issued', $quotation->contact_phone, $quotation->contact_email,
            'Baking House quotation '.$quotation->number,
            "Baking House: your quotation {$quotation->number} is ready (".Taka::format($total).', valid until '.$validUntil->timezone('Asia/Dhaka')->format('d M Y').'). Review it in your business dashboard.');

        return $quotation;
    }

    /**
     * @throws ValidationException
     */
    public function accept(Quotation $quotation, User $user, Organization $organization): Quotation
    {
        $this->assertBuyer($quotation, $user, $organization);
        if ($quotation->status !== QuotationStatus::Quoted || ! $quotation->isCurrent()) {
            throw ValidationException::withMessages(['status' => __('Only a current quote can be accepted.')]);
        }

        $this->move($quotation, QuotationStatus::Quoted, QuotationStatus::Accepted, $user, 'quotation.accepted');

        return $quotation->refresh();
    }

    /**
     * @throws ValidationException
     */
    public function decline(Quotation $quotation, User $user, Organization $organization, ?string $reason): Quotation
    {
        $this->assertBuyer($quotation, $user, $organization);
        if (! in_array($quotation->status, [QuotationStatus::Requested, QuotationStatus::Quoted, QuotationStatus::Accepted], true)) {
            throw ValidationException::withMessages(['status' => __('This quotation can no longer be declined.')]);
        }

        $this->move($quotation, $quotation->status, QuotationStatus::Declined, $user, 'quotation.declined', $reason);

        return $quotation->refresh();
    }

    /**
     * Staff closes a lead or withdraws a quote that was not ordered.
     *
     * @throws ValidationException
     */
    public function close(Quotation $quotation, User $staff, string $reason): Quotation
    {
        if (! $quotation->status->isOpen()) {
            throw ValidationException::withMessages(['status' => __('This quotation is already closed.')]);
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => __('Please give a reason.')]);
        }

        $this->move($quotation, $quotation->status, QuotationStatus::Closed, $staff, 'quotation.closed', $reason);

        return $quotation->refresh();
    }

    /**
     * Mark quotes past their validity as expired. Returns how many were expired.
     */
    public function expireStale(): int
    {
        $count = 0;
        Quotation::query()
            ->whereIn('status', [QuotationStatus::Quoted->value, QuotationStatus::Accepted->value])
            ->where('valid_until', '<=', now())
            ->orderBy('id')
            ->each(function (Quotation $quotation) use (&$count): void {
                $updated = Quotation::query()->whereKey($quotation->id)->where('status', $quotation->status->value)
                    ->update(['status' => QuotationStatus::Expired->value, 'decided_at' => now(), 'updated_at' => now()]);
                if ($updated === 1) {
                    $count++;
                    $this->audit->record('quotation.expired', $quotation, ['status' => $quotation->status->value], ['status' => QuotationStatus::Expired->value]);
                    $this->crm->quotationChanged($quotation, 'expired');
                }
            });

        return $count;
    }

    /**
     * @throws ValidationException
     */
    private function assertBuyer(Quotation $quotation, User $user, Organization $organization): void
    {
        abort_unless($quotation->organization_id === $organization->id, 404);

        if (! ($user->roleIn($organization)?->canPlaceOrders() ?? false)) {
            throw ValidationException::withMessages(['status' => __('Your role cannot accept or decline quotations.')]);
        }
    }

    /**
     * @throws ValidationException
     */
    private function move(Quotation $quotation, QuotationStatus $from, QuotationStatus $to, User $actor, string $action, ?string $reason = null): void
    {
        $updated = Quotation::query()->whereKey($quotation->id)->where('status', $from->value)
            ->update(['status' => $to->value, 'decided_at' => now(), 'decided_by' => $actor->id, 'updated_at' => now()]);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['status' => __('This quotation was changed by someone else. Reload and try again.')]);
        }

        $this->audit->record($action, $quotation, ['status' => $from->value], ['status' => $to->value], $reason, $actor->id);
        $this->crm->quotationChanged($quotation->refresh(), $to->value);
    }

    private function newNumber(): string
    {
        $alphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $suffix = '';
            for ($i = 0; $i < 5; $i++) {
                $suffix .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
            $number = 'RFQ'.now('Asia/Dhaka')->format('ymd').'-'.$suffix;
            if (! Quotation::query()->where('number', $number)->exists()) {
                return $number;
            }
        }

        throw new RuntimeException('Could not allocate a quotation number.');
    }
}

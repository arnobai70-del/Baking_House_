<?php

namespace App\Services\Pricing;

use App\Models\ScheduledPriceChange;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Applies scheduled standard-price changes once their effective time arrives. Each change is
 * claimed with a guarded UPDATE (pending → applied) so it can never be applied twice, and the
 * before/after values plus the original reason and author go to the audit log.
 */
class ScheduledPriceService
{
    public function __construct(private AuditLogger $audit) {}

    public function applyDue(): int
    {
        $applied = 0;

        ScheduledPriceChange::query()
            ->where('status', ScheduledPriceChange::STATUS_PENDING)
            ->where('effective_at', '<=', now())
            ->orderBy('effective_at')
            ->orderBy('id')
            ->each(function (ScheduledPriceChange $change) use (&$applied): void {
                $applied += $this->apply($change) ? 1 : 0;
            });

        return $applied;
    }

    private function apply(ScheduledPriceChange $change): bool
    {
        return DB::transaction(function () use ($change): bool {
            $claimed = ScheduledPriceChange::query()
                ->whereKey($change->id)
                ->where('status', ScheduledPriceChange::STATUS_PENDING)
                ->update(['status' => ScheduledPriceChange::STATUS_APPLIED, 'applied_at' => now(), 'updated_at' => now()]);

            if ($claimed !== 1) {
                return false;
            }

            $variant = $change->variant()->first();
            if ($variant === null || ! in_array($change->field, ScheduledPriceChange::FIELDS, true)) {
                return true;
            }

            $before = $variant->{$change->field};
            $variant->{$change->field} = $change->new_value_paisa;
            $variant->save();

            $this->audit->record(
                'product.price_changed',
                $variant->product()->first(),
                ['sku' => $variant->sku, $change->field => $before],
                ['sku' => $variant->sku, $change->field => $change->new_value_paisa, 'scheduled_change_id' => $change->id],
                'Scheduled: '.$change->reason,
                $change->created_by,
            );

            return true;
        });
    }
}

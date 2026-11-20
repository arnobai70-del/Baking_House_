<?php

namespace App\Services\Orders;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\QuotationStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Promotion;
use App\Models\PromotionRedemption;
use App\Models\Quotation;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Crm\CrmService;
use App\Services\Notifications\OrderNotifier;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Fulfillment transitions and cancellation. Every change is a guarded UPDATE from the expected
 * current status (so two staff clicking at once cannot double-apply), recorded on the order
 * timeline and in the audit log.
 */
class OrderService
{
    public function __construct(
        private OrderStock $stock,
        private AuditLogger $audit,
        private OrderNotifier $notifier,
    ) {}

    /**
     * Staff moves an order forward: confirmed → processing → packed → shipped → delivered.
     *
     * @throws ValidationException
     */
    public function transition(Order $order, OrderStatus $to, User $actor, ?string $note = null): Order
    {
        $from = $order->status;

        if ($to === OrderStatus::Cancelled) {
            return $this->cancel($order, (string) $note, $actor, byCustomer: false);
        }
        if ($to === OrderStatus::Confirmed || ! $from->canTransitionTo($to)) {
            throw ValidationException::withMessages(['status' => "An order that is {$from->label()} cannot move to {$to->label()}."]);
        }

        $this->moveStatus($order, $from, $to, $actor, $note);
        $this->notifier->statusChanged($order, $to);

        return $order;
    }

    /**
     * Cancel and return stock. Customers may cancel only before preparation starts; staff may
     * cancel until the order ships. Paid orders are flagged "refund due" for staff to refund.
     *
     * @throws ValidationException
     */
    public function cancel(Order $order, string $reason, ?User $actor, bool $byCustomer): Order
    {
        $from = $order->status;
        $allowed = $byCustomer ? $from->customerCanCancel() : $from->canTransitionTo(OrderStatus::Cancelled);
        if (! $allowed) {
            throw ValidationException::withMessages(['status' => $byCustomer
                ? 'This order can no longer be cancelled online. Please contact us.'
                : "An order that is {$from->label()} cannot be cancelled."]);
        }
        if (mb_strlen(trim($reason)) < 3) {
            throw ValidationException::withMessages(['reason' => 'Please give a reason for cancelling.']);
        }

        DB::transaction(function () use ($order, $from, $reason, $actor, $byCustomer): void {
            $this->moveStatus($order, $from, OrderStatus::Cancelled, $actor, $reason, extra: [
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ]);

            $this->stock->restock($order, $actor);

            Payment::query()->where('order_id', $order->id)->where('status', PaymentRecordStatus::Initiated->value)
                ->update(['status' => PaymentRecordStatus::Cancelled->value, 'updated_at' => now()]);

            if (! $order->isPaid() && $order->payment_status !== PaymentStatus::Unpaid) {
                $this->setPaymentStatus($order, PaymentStatus::Failed, $actor, 'Order cancelled before payment');
            }

            $this->releasePromotions($order);
            $this->reopenQuotations($order);

            $this->audit->record('order.cancelled', $order, ['status' => $from->value], [
                'status' => OrderStatus::Cancelled->value,
                'by' => $byCustomer ? 'customer' : ($actor === null ? 'system' : 'staff'),
                'refund_due' => $order->isPaid() ? $order->total_paisa - $order->refunded_paisa : 0,
            ], $reason, $actor?->id);
        });

        $this->notifier->statusChanged($order, OrderStatus::Cancelled);

        return $order;
    }

    /**
     * Staff resolves an order in "payment review" (paid after its hold expired): allocate stock
     * now if it exists, otherwise it stays in review for cancellation + refund.
     *
     * @throws ValidationException
     */
    public function retryAllocation(Order $order, User $actor): Order
    {
        if ($order->status !== OrderStatus::PaymentReview) {
            throw ValidationException::withMessages(['status' => 'Only orders in payment review need allocation.']);
        }
        if (! $this->stock->commitOrReallocate($order, $actor)) {
            throw ValidationException::withMessages(['status' => 'Stock is still not available. Cancel and refund, or restock first.']);
        }

        $this->moveStatus($order, OrderStatus::PaymentReview, OrderStatus::Confirmed, $actor, 'Stock allocated after review', ['confirmed_at' => now()]);
        $this->notifier->statusChanged($order, OrderStatus::Confirmed);

        return $order;
    }

    /**
     * Cancel stale unpaid orders: online orders whose hold expired beyond the grace period and
     * bank-transfer orders past their payment window. Returns the number cancelled.
     */
    public function expireUnpaid(): int
    {
        $count = 0;
        $onlineCutoff = now()->subMinutes((int) config('commerce.online_unpaid_grace_minutes', 60));
        $bankCutoff = now()->subHours((int) config('commerce.bank_transfer_hours', 48));

        Order::query()
            ->where('status', OrderStatus::PendingPayment->value)
            ->whereIn('payment_status', [PaymentStatus::Pending->value, PaymentStatus::Failed->value])
            ->where(function ($query) use ($onlineCutoff, $bankCutoff): void {
                $query->where(fn ($q) => $q->whereIn('payment_method', ['bkash', 'nagad', 'card'])->where('reservation_expires_at', '<', $onlineCutoff))
                    ->orWhere(fn ($q) => $q->where('payment_method', PaymentMethod::BankTransfer->value)->where('placed_at', '<', $bankCutoff));
            })
            ->orderBy('id')
            ->each(function (Order $order) use (&$count): void {
                try {
                    $this->cancel($order, 'Payment not received in time', null, byCustomer: false);
                    $count++;
                } catch (ValidationException) {
                    // Status changed concurrently (e.g. paid just now) — leave it.
                }
            });

        return $count;
    }

    /**
     * Guarded status change + timeline entry.
     *
     * @param  array<string, mixed>  $extra
     *
     * @throws ValidationException
     */
    public function moveStatus(Order $order, OrderStatus $from, OrderStatus $to, ?User $actor, ?string $note, array $extra = []): void
    {
        $updated = Order::query()->whereKey($order->id)->where('status', $from->value)
            ->update(['status' => $to->value, 'updated_at' => now()] + $extra);

        if ($updated !== 1) {
            throw ValidationException::withMessages(['status' => 'The order was changed by someone else. Reload and try again.']);
        }

        $order->forceFill(['status' => $to] + $extra)->syncOriginal();
        $order->events()->forceCreate(['kind' => 'fulfillment', 'from_status' => $from->value, 'to_status' => $to->value, 'actor_id' => $actor?->id, 'note' => $note]);

        if ($to !== OrderStatus::Cancelled) {
            $this->audit->record('order.status_changed', $order, ['status' => $from->value], ['status' => $to->value], $note, $actor?->id);
        }
    }

    public function setPaymentStatus(Order $order, PaymentStatus $to, ?User $actor, ?string $note): void
    {
        $from = $order->payment_status;
        if ($from === $to) {
            return;
        }

        Order::query()->whereKey($order->id)->update(['payment_status' => $to->value, 'updated_at' => now()]);
        $order->forceFill(['payment_status' => $to])->syncOriginal();
        $order->events()->forceCreate(['kind' => 'payment', 'from_status' => $from->value, 'to_status' => $to->value, 'actor_id' => $actor?->id, 'note' => $note]);
    }

    /**
     * A cancelled order hands its quotation back to the buyer: accepted again while still valid
     * (so they can re-order at the quoted price), otherwise expired.
     */
    private function reopenQuotations(Order $order): void
    {
        foreach (Quotation::query()->where('order_id', $order->id)->where('status', QuotationStatus::Ordered->value)->get() as $quotation) {
            $status = $quotation->valid_until?->isFuture() ? QuotationStatus::Accepted : QuotationStatus::Expired;
            $quotation->forceFill(['status' => $status, 'order_id' => null])->save();
            $this->audit->record('quotation.reopened', $quotation, ['order' => $order->number], ['status' => $status->value]);
            app(CrmService::class)->quotationChanged($quotation, $status === QuotationStatus::Accepted ? 'reopened' : 'expired');
        }
    }

    /**
     * A cancelled order gives its promotion usage back.
     */
    private function releasePromotions(Order $order): void
    {
        PromotionRedemption::query()->where('reference', $order->number)->get()->each(function (PromotionRedemption $redemption): void {
            Promotion::query()->whereKey($redemption->promotion_id)->where('used_count', '>', 0)->decrement('used_count');
            $redemption->delete();
        });
    }
}

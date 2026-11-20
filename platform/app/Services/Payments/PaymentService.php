<?php

namespace App\Services\Payments;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\Refund;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Notifications\OrderNotifier;
use App\Services\Orders\OrderService;
use App\Services\Orders\OrderStock;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Payment lifecycle, kept separate from fulfillment status.
 *
 * - Provider callbacks are verified (signature + freshness), stored once per (provider, event id)
 *   and applied at most once: a replayed callback is acknowledged but changes nothing.
 * - Capture is a guarded UPDATE initiated → paid, so a payment can never be captured twice.
 * - The amount and currency in the callback must match the payment exactly.
 * - Late success (hold expired): stock is re-allocated if available; otherwise the order goes to
 *   "payment review" (never confirmed against missing stock). Success on a cancelled order is
 *   recorded and flagged for refund.
 */
class PaymentService
{
    public function __construct(
        private PaymentGatewayFactory $gateways,
        private OrderStock $stock,
        private OrderService $orders,
        private AuditLogger $audit,
        private OrderNotifier $notifier,
    ) {}

    /**
     * URL of the (test) hosted payment page for the order's current online payment, creating a new
     * attempt if the last one failed.
     *
     * @throws ValidationException
     */
    public function checkoutUrl(Order $order): string
    {
        if (! $order->payment_method->isOnline() || $order->status !== OrderStatus::PendingPayment || $order->isPaid()) {
            throw ValidationException::withMessages(['payment' => 'This order does not need an online payment.']);
        }

        $payment = $order->payments()->reorder()->where('status', PaymentRecordStatus::Initiated->value)->latest('id')->first();
        if ($payment === null) {
            $payment = new Payment;
            $payment->forceFill([
                'order_id' => $order->id, 'provider' => $order->payment_method, 'mode' => 'test',
                'status' => PaymentRecordStatus::Initiated, 'amount_paisa' => $order->total_paisa,
                'reference' => 'PAY-'.Str::upper(Str::random(16)),
            ])->save();
            $this->orders->setPaymentStatus($order, PaymentStatus::Pending, null, 'New payment attempt');
        }

        return $this->gateways->for($order->payment_method)->checkoutUrl($payment);
    }

    /**
     * Process a provider callback. Returns [http status, result code].
     *
     * @param  array<string, string|null>  $headers
     * @return array{0: int, 1: string}
     */
    public function handleCallback(string $providerSlug, string $rawBody, array $headers): array
    {
        $method = PaymentMethod::tryFrom($providerSlug);
        if ($method === null || ! $method->isOnline()) {
            return [404, 'unknown_provider'];
        }

        $gateway = $this->gateways->for($method);
        $decoded = json_decode($rawBody, true);
        $eventId = is_array($decoded) && is_string($decoded['event_id'] ?? null) ? substr($decoded['event_id'], 0, 100) : null;

        try {
            $payload = $gateway->verifyCallback($rawBody, $headers);
        } catch (InvalidCallbackException $e) {
            $this->recordEvent($method, 'invalid-'.Str::uuid(), null, (string) ($decoded['type'] ?? 'unknown'), false, 'rejected_'.Str::snake($e->getMessage()), $decoded);

            return [401, 'invalid_signature'];
        }

        $payment = Payment::query()->where('reference', (string) $payload['reference'])->where('provider', $method->value)->first();

        try {
            $event = $this->recordEvent($method, (string) $eventId, $payment?->id, (string) $payload['type'], true, 'received', $payload);
        } catch (UniqueConstraintViolationException) {
            return [200, 'duplicate'];
        }

        $result = match (true) {
            $payment === null => 'unknown_payment',
            $payload['type'] === 'payment.succeeded' => $this->applySuccess($payment, $payload),
            in_array($payload['type'], ['payment.failed', 'payment.cancelled'], true) => $this->applyFailure($payment, $payload['type'] === 'payment.cancelled' ? PaymentRecordStatus::Cancelled : PaymentRecordStatus::Failed),
            default => 'ignored_type',
        };

        PaymentEvent::query()->whereKey($event->id)->update(['result' => $result]);

        return [200, $result];
    }

    /**
     * Staff confirms a bank transfer after checking the bank statement.
     *
     * @throws ValidationException
     */
    public function confirmBankTransfer(Order $order, int $amountPaisa, string $bankReference, User $actor): void
    {
        if ($order->payment_method !== PaymentMethod::BankTransfer || $order->isPaid() || $order->status === OrderStatus::Cancelled) {
            throw ValidationException::withMessages(['payment' => 'This order is not awaiting a bank transfer.']);
        }
        if ($amountPaisa !== $order->total_paisa) {
            throw ValidationException::withMessages(['amount' => 'The amount received must equal the order total. Record part-payments with the customer first.']);
        }

        $payment = $order->payments()->reorder()->where('status', PaymentRecordStatus::Initiated->value)->latest('id')->firstOrFail();

        DB::transaction(function () use ($order, $payment, $bankReference, $actor): void {
            $this->capture($payment, $bankReference, $actor);
            $this->orders->setPaymentStatus($order, PaymentStatus::Paid, $actor, 'Bank transfer confirmed: '.$bankReference);
            $this->orders->moveStatus($order, OrderStatus::PendingPayment, OrderStatus::Confirmed, $actor, 'Payment received', ['confirmed_at' => now()]);
            $this->audit->record('payment.bank_transfer_confirmed', $order, after: ['amount_paisa' => $order->total_paisa, 'bank_reference' => $bankReference], actorId: $actor->id);
        });

        $this->notifier->paymentReceived($order);
        $this->notifier->statusChanged($order, OrderStatus::Confirmed);
    }

    /**
     * Staff records cash collected on delivery.
     *
     * @throws ValidationException
     */
    public function markCashCollected(Order $order, User $actor): void
    {
        if ($order->payment_method !== PaymentMethod::CashOnDelivery || $order->isPaid()
            || ! in_array($order->status, [OrderStatus::Shipped, OrderStatus::Delivered], true)) {
            throw ValidationException::withMessages(['payment' => 'Cash can be recorded only for shipped or delivered cash-on-delivery orders.']);
        }

        $payment = $order->payments()->reorder()->where('status', PaymentRecordStatus::Initiated->value)->latest('id')->firstOrFail();

        DB::transaction(function () use ($order, $payment, $actor): void {
            $this->capture($payment, 'COD-'.$order->number, $actor);
            $this->orders->setPaymentStatus($order, PaymentStatus::Paid, $actor, 'Cash collected on delivery');
            $this->audit->record('payment.cod_collected', $order, after: ['amount_paisa' => $order->total_paisa], actorId: $actor->id);
        });
    }

    /**
     * Record a refund (and, for online test payments, call the gateway). Never more than paid.
     *
     * @throws ValidationException
     */
    public function refund(Order $order, int $amountPaisa, string $reason, User $actor): Refund
    {
        $payment = $order->payments()->reorder()->whereIn('status', [PaymentRecordStatus::Paid->value, PaymentRecordStatus::PartiallyRefunded->value])->latest('id')->first();

        if ($payment === null) {
            throw ValidationException::withMessages(['amount' => 'There is no captured payment to refund.']);
        }
        if ($amountPaisa < 1 || $amountPaisa > $payment->refundablePaisa()) {
            throw ValidationException::withMessages(['amount' => 'Refund must be between ৳0.01 and the refundable amount.']);
        }
        if (mb_strlen(trim($reason)) < 5) {
            throw ValidationException::withMessages(['reason' => 'Give a reason for the refund.']);
        }

        $providerReference = $payment->provider->isOnline() ? $this->gateways->for($payment->provider)->refund($payment, $amountPaisa) : null;

        $refund = DB::transaction(function () use ($order, $payment, $amountPaisa, $reason, $actor, $providerReference): Refund {
            $updated = Payment::query()->whereKey($payment->id)->whereRaw('amount_paisa - refunded_paisa >= ?', [$amountPaisa])
                ->update(['refunded_paisa' => DB::raw('refunded_paisa + '.$amountPaisa), 'updated_at' => now()]);
            if ($updated !== 1) {
                throw ValidationException::withMessages(['amount' => 'Refund exceeds the refundable amount.']);
            }
            $payment->refresh();
            $fully = $payment->refunded_paisa >= $payment->amount_paisa;
            $payment->forceFill(['status' => $fully ? PaymentRecordStatus::Refunded : PaymentRecordStatus::PartiallyRefunded])->save();

            Order::query()->whereKey($order->id)->increment('refunded_paisa', $amountPaisa);
            $order->refresh();
            $this->orders->setPaymentStatus($order, $order->refunded_paisa >= $order->total_paisa ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded, $actor, 'Refund recorded');

            $refund = new Refund;
            $refund->forceFill([
                'payment_id' => $payment->id, 'order_id' => $order->id, 'amount_paisa' => $amountPaisa,
                'status' => 'recorded', 'reason' => $reason, 'provider_reference' => $providerReference, 'actor_id' => $actor->id,
            ])->save();

            $this->audit->record('payment.refunded', $order, after: ['amount_paisa' => $amountPaisa, 'provider_reference' => $providerReference], reason: $reason, actorId: $actor->id);

            return $refund;
        });

        $this->notifier->refundRecorded($order, $amountPaisa);

        return $refund;
    }

    /**
     * Payments needing attention: mismatches between payments, orders and refunds.
     *
     * @return list<array{order: string, payment: string, issue: string}>
     */
    public function reconciliationIssues(): array
    {
        $issues = [];

        Payment::query()->with('order')->where('created_at', '>=', now()->subDays(90))->orderBy('id')->each(function (Payment $payment) use (&$issues): void {
            $order = $payment->order;
            $add = function (string $issue) use (&$issues, $order, $payment): void {
                $issues[] = ['order' => (string) $order?->number, 'payment' => $payment->reference, 'issue' => $issue];
            };

            if ($payment->status === PaymentRecordStatus::Paid && $order !== null && ! $order->isPaid() && $order->payment_status !== PaymentStatus::Refunded) {
                $add('Payment captured but order not marked paid.');
            }
            if ($payment->status === PaymentRecordStatus::Paid && $order?->status === OrderStatus::Cancelled && $order->refunded_paisa < $order->total_paisa) {
                $add('Paid order is cancelled — refund due.');
            }
            if ($payment->status === PaymentRecordStatus::Initiated && $payment->provider->isOnline() && $payment->created_at < now()->subHours(2)) {
                $add('Online payment still initiated after 2 hours.');
            }
            if ((int) $payment->refunds()->sum('amount_paisa') !== $payment->refunded_paisa) {
                $add('Refund records do not add up to the refunded amount.');
            }
            if (($payment->meta['amount_mismatch'] ?? false) === true) {
                $add('Provider reported a different amount — not captured.');
            }
        });

        foreach (Order::query()->where('status', OrderStatus::PaymentReview->value)->get() as $order) {
            $issues[] = ['order' => $order->number, 'payment' => '', 'issue' => 'Order in payment review (paid after stock hold expired).'];
        }

        return $issues;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function applySuccess(Payment $payment, array $payload): string
    {
        if ((int) ($payload['amount_paisa'] ?? -1) !== $payment->amount_paisa || ($payload['currency'] ?? '') !== $payment->currency) {
            $payment->forceFill(['meta' => array_merge($payment->meta ?? [], ['amount_mismatch' => true])])->save();
            $this->audit->record('payment.amount_mismatch', $payment->order()->first(), after: ['expected' => $payment->amount_paisa, 'reported' => $payload['amount_paisa'] ?? null]);

            return 'amount_mismatch';
        }

        $order = $payment->order()->firstOrFail();

        return DB::transaction(function () use ($payment, $payload, $order): string {
            if (! $this->capture($payment, isset($payload['transaction_id']) ? (string) $payload['transaction_id'] : null, null)) {
                return 'already_processed';
            }

            $this->orders->setPaymentStatus($order, PaymentStatus::Paid, null, 'Verified '.$payment->provider->label().' callback');

            if ($order->status === OrderStatus::Cancelled) {
                $this->audit->record('payment.paid_after_cancel', $order, after: ['refund_due_paisa' => $payment->amount_paisa]);

                return 'paid_after_cancel_refund_due';
            }

            if ($order->status === OrderStatus::PendingPayment) {
                if ($this->stock->commitOrReallocate($order)) {
                    $this->orders->moveStatus($order, OrderStatus::PendingPayment, OrderStatus::Confirmed, null, 'Payment verified', ['confirmed_at' => now()]);
                    DB::afterCommit(function () use ($order): void {
                        $this->notifier->paymentReceived($order);
                        $this->notifier->statusChanged($order, OrderStatus::Confirmed);
                    });

                    return 'paid_confirmed';
                }

                $this->orders->moveStatus($order, OrderStatus::PendingPayment, OrderStatus::PaymentReview, null, 'Paid after stock hold expired; stock unavailable');
                $this->audit->record('order.payment_review', $order, after: ['reason' => 'stock_unavailable_after_late_payment']);

                return 'paid_stock_unavailable_review';
            }

            return 'paid';
        });
    }

    private function applyFailure(Payment $payment, PaymentRecordStatus $status): string
    {
        $updated = Payment::query()->whereKey($payment->id)->where('status', PaymentRecordStatus::Initiated->value)
            ->update(['status' => $status->value, 'updated_at' => now()]);
        if ($updated !== 1) {
            return 'already_processed';
        }

        $order = $payment->order()->firstOrFail();
        if (! $order->isPaid() && $order->status === OrderStatus::PendingPayment) {
            $this->orders->setPaymentStatus($order, PaymentStatus::Failed, null, $payment->provider->label().' payment '.$status->value);
        }

        return 'payment_'.$status->value;
    }

    /**
     * One-way, at-most-once capture.
     */
    private function capture(Payment $payment, ?string $providerTransactionId, ?User $actor): bool
    {
        $updated = Payment::query()->whereKey($payment->id)->where('status', PaymentRecordStatus::Initiated->value)
            ->update(['status' => PaymentRecordStatus::Paid->value, 'provider_transaction_id' => $providerTransactionId, 'paid_at' => now(), 'updated_at' => now()]);

        if ($updated === 1) {
            $payment->refresh();
            $this->audit->record('payment.captured', $payment->order()->first(), after: [
                'payment' => $payment->reference, 'provider' => $payment->provider->value, 'amount_paisa' => $payment->amount_paisa, 'mode' => $payment->mode,
            ], actorId: $actor?->id);
        }

        return $updated === 1;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     */
    private function recordEvent(PaymentMethod $method, string $eventId, ?int $paymentId, string $type, bool $valid, string $result, ?array $payload): PaymentEvent
    {
        $event = new PaymentEvent;
        $event->forceFill([
            'provider' => $method->value,
            'event_id' => $eventId,
            'payment_id' => $paymentId,
            'event_type' => substr($type, 0, 32),
            'signature_valid' => $valid,
            'result' => $result,
            'payload' => array_intersect_key($payload ?? [], array_flip(['event_id', 'type', 'reference', 'transaction_id', 'amount_paisa', 'currency'])),
        ])->save();

        return $event;
    }
}

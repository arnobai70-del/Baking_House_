<?php

namespace App\Enums;

/**
 * Fulfillment status (separate from payment status). Transitions are explicit; anything not
 * listed in allowedNext() is rejected.
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';
    case PaymentReview = 'payment_review';
    case Confirmed = 'confirmed';
    case Processing = 'processing';
    case Packed = 'packed';
    case Shipped = 'shipped';
    case Delivered = 'delivered';
    case Cancelled = 'cancelled';

    /**
     * @return list<self>
     */
    public function allowedNext(): array
    {
        return match ($this) {
            self::PendingPayment => [self::Confirmed, self::Cancelled],
            self::PaymentReview => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::Processing, self::Cancelled],
            self::Processing => [self::Packed, self::Cancelled],
            self::Packed => [self::Shipped, self::Cancelled],
            self::Shipped => [self::Delivered],
            self::Delivered, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next, $this->allowedNext(), true);
    }

    /**
     * Customers may cancel their own order only before it is being prepared.
     */
    public function customerCanCancel(): bool
    {
        return in_array($this, [self::PendingPayment, self::Confirmed], true);
    }

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::PaymentReview => 'Payment under review',
            self::Confirmed => 'Confirmed',
            self::Processing => 'Processing',
            self::Packed => 'Packed',
            self::Shipped => 'Shipped',
            self::Delivered => 'Delivered',
            self::Cancelled => 'Cancelled',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Delivered, self::Confirmed => 'ok',
            self::Cancelled => '',
            self::PaymentReview, self::PendingPayment => 'warn',
            default => 'info',
        };
    }
}

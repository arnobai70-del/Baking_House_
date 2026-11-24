<?php

namespace App\Enums;

/**
 * Order-level payment status (separate from fulfillment status).
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case PartiallyPaid = 'partially_paid'; // advance captured, balance due (custom cakes)
    case Paid = 'paid';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Pending => 'Payment pending',
            self::PartiallyPaid => 'Advance paid, balance due',
            self::Paid => 'Paid',
            self::Failed => 'Payment failed',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }
}

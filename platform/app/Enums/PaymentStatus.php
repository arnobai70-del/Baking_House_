<?php

namespace App\Enums;

/**
 * Order-level payment status (separate from fulfillment status).
 */
enum PaymentStatus: string
{
    case Unpaid = 'unpaid';
    case Pending = 'pending';
    case Paid = 'paid';
    case Failed = 'failed';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Unpaid',
            self::Pending => 'Payment pending',
            self::Paid => 'Paid',
            self::Failed => 'Payment failed',
            self::PartiallyRefunded => 'Partially refunded',
            self::Refunded => 'Refunded',
        };
    }
}

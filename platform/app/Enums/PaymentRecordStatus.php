<?php

namespace App\Enums;

/**
 * Status of one payment attempt. Initiated → Paid / Failed / Cancelled is a one-way, guarded
 * transition, so a payment can be captured at most once.
 */
enum PaymentRecordStatus: string
{
    case Initiated = 'initiated';
    case Paid = 'paid';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
    case PartiallyRefunded = 'partially_refunded';
    case Refunded = 'refunded';
}

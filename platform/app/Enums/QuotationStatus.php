<?php

namespace App\Enums;

/**
 * RFQ lifecycle: requested → quoted → accepted → ordered. A quote can also be declined by the
 * buyer, closed by staff, or expire after its validity date.
 */
enum QuotationStatus: string
{
    case Requested = 'requested';
    case Quoted = 'quoted';
    case Accepted = 'accepted';
    case Ordered = 'ordered';
    case Declined = 'declined';
    case Closed = 'closed';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::Requested => 'Requested',
            self::Quoted => 'Quote issued',
            self::Accepted => 'Accepted',
            self::Ordered => 'Ordered',
            self::Declined => 'Declined',
            self::Closed => 'Closed',
            self::Expired => 'Expired',
        };
    }

    public function tone(): string
    {
        return match ($this) {
            self::Requested => 'warn',
            self::Quoted, self::Accepted => 'info',
            self::Ordered => 'ok',
            self::Declined, self::Closed, self::Expired => '',
        };
    }

    public function isOpen(): bool
    {
        return in_array($this, [self::Requested, self::Quoted, self::Accepted], true);
    }
}

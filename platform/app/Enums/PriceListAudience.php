<?php

namespace App\Enums;

/**
 * Who a price list applies to. Precedence (highest first): Contract → Business/Reseller → Campaign → Retail.
 * Contract lists belong to exactly one organization (customer-specific pricing).
 */
enum PriceListAudience: string
{
    case Retail = 'retail';
    case Campaign = 'campaign';
    case Business = 'business';
    case Reseller = 'reseller';
    case Contract = 'contract';

    public function label(): string
    {
        return match ($this) {
            self::Retail => 'Retail volume pricing',
            self::Campaign => 'Campaign (all customers, time-limited)',
            self::Business => 'Business accounts',
            self::Reseller => 'Resellers',
            self::Contract => 'Contract (one organization)',
        };
    }

    /**
     * Rate-source label used in price breakdowns.
     */
    public function source(): string
    {
        return match ($this) {
            self::Retail => 'retail-tier',
            self::Campaign => 'campaign',
            self::Business => 'business-tier',
            self::Reseller => 'reseller-tier',
            self::Contract => 'contract',
        };
    }
}

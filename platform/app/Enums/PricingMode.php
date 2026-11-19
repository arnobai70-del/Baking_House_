<?php

namespace App\Enums;

/**
 * Per-variant pricing mode (blueprint §05). VARIANT pricing is modelled by having several variants.
 */
enum PricingMode: string
{
    case Fixed = 'fixed';
    case Weight = 'weight';
    case QuoteOnly = 'quote_only';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Fixed price per unit',
            self::Weight => 'Sold by weight (per kg)',
            self::QuoteOnly => 'Quote only',
        };
    }
}

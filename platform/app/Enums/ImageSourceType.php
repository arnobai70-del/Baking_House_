<?php

namespace App\Enums;

/**
 * Where a product photo came from. Every option is a real photograph; AI-generated or
 * AI-altered images are not accepted under any source.
 */
enum ImageSourceType: string
{
    case OwnPhotography = 'own_photography';
    case Commissioned = 'commissioned';
    case Supplier = 'supplier';
    case LicensedStock = 'licensed_stock';

    public function label(): string
    {
        return match ($this) {
            self::OwnPhotography => 'Own photography (Baking House)',
            self::Commissioned => 'Commissioned photographer',
            self::Supplier => 'Supplier / manufacturer photo (with permission)',
            self::LicensedStock => 'Licensed stock photograph',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Where a product image came from. Since owner decision D-074 (2026-11-22) AI-generated images are
 * accepted too, but only when declared as such: provenance stays recorded for every image.
 */
enum ImageSourceType: string
{
    case OwnPhotography = 'own_photography';
    case Commissioned = 'commissioned';
    case Supplier = 'supplier';
    case LicensedStock = 'licensed_stock';
    case AiGenerated = 'ai_generated';

    public function label(): string
    {
        return match ($this) {
            self::OwnPhotography => 'Own photography ('.config('brand.name').')',
            self::Commissioned => 'Commissioned photographer',
            self::Supplier => 'Supplier / manufacturer photo (with permission)',
            self::LicensedStock => 'Licensed stock photograph',
            self::AiGenerated => 'AI-generated image (tool recorded as creator)',
        };
    }
}

<?php

namespace App\Enums;

/**
 * Sellable unit of a variant. Weight variants sell in grams (shown as g below 1000 g, kg above);
 * everything else is counted in whole units. Pack-type units carry a pack_size.
 */
enum SellingUnit: string
{
    case Gram = 'gram';
    case Piece = 'piece';
    case Pack = 'pack';
    case Box = 'box';
    case Set = 'set';
    case Carton = 'carton';

    public function label(): string
    {
        return match ($this) {
            self::Gram => 'Weight (g / kg)',
            self::Piece => 'Piece',
            self::Pack => 'Pack',
            self::Box => 'Box',
            self::Set => 'Set',
            self::Carton => 'Carton',
        };
    }

    public function isWeight(): bool
    {
        return $this === self::Gram;
    }

    public function hasPackSize(): bool
    {
        return in_array($this, [self::Pack, self::Box, self::Carton], true);
    }
}

<?php

namespace App\Enums;

use App\Support\Taka;

/**
 * Percentage values are stored in basis points (1250 = 12.5%); fixed amounts in paisa.
 */
enum DiscountType: string
{
    case Percentage = 'percentage';
    case FixedAmount = 'fixed_amount';

    public function describe(int $value): string
    {
        return match ($this) {
            self::Percentage => rtrim(rtrim(number_format($value / 100, 2, '.', ''), '0'), '.').'% off',
            self::FixedAmount => Taka::format($value).' off',
        };
    }
}

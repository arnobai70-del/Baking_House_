<?php

namespace App\Support;

/**
 * BDT money helpers. Amounts are integer paisa (৳1 = 100 paisa) — never floats.
 */
class Taka
{
    /**
     * Format paisa as "৳1,28,900" or "৳812.50" (South Asian digit grouping).
     */
    public static function format(int $paisa): string
    {
        $sign = $paisa < 0 ? '-' : '';
        $paisa = abs($paisa);
        $taka = intdiv($paisa, 100);
        $rest = $paisa % 100;

        $formatted = $sign.'৳'.self::groupIndian((string) $taka);

        return $rest === 0 ? $formatted : $formatted.'.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Integer division rounding half up (non-negative operands).
     */
    public static function divideRoundHalfUp(int $numerator, int $denominator): int
    {
        return intdiv($numerator + intdiv($denominator, 2), $denominator);
    }

    private static function groupIndian(string $digits): string
    {
        if (strlen($digits) <= 3) {
            return $digits;
        }

        $lastThree = substr($digits, -3);
        $leading = substr($digits, 0, -3);
        $pairs = str_split(strrev($leading), 2);

        return strrev(implode(',', $pairs)).','.$lastThree;
    }
}

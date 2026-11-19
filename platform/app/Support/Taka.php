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
     * Parse admin input like "650", "812.50" or "1,250.5" into paisa without floats.
     * Returns null for anything that is not a non-negative amount with at most 2 decimals.
     */
    public static function parse(?string $input): ?int
    {
        $text = str_replace([',', '৳', ' '], '', trim((string) $input));

        if (preg_match('/^(\d{1,12})(?:\.(\d{1,2}))?$/', $text, $matches) !== 1) {
            return null;
        }

        return (int) $matches[1] * 100 + (int) str_pad($matches[2] ?? '', 2, '0');
    }

    /**
     * Paisa back to a plain admin input value: 81250 → "812.50", 65000 → "650".
     */
    public static function toInput(?int $paisa): string
    {
        if ($paisa === null) {
            return '';
        }

        $rest = $paisa % 100;

        return intdiv($paisa, 100).($rest === 0 ? '' : '.'.str_pad((string) $rest, 2, '0', STR_PAD_LEFT));
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

<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer-gram weights. Mirrors prototype/assets/js/weight-pricing.js (docs/pricing-rules.md).
 */
class Weight
{
    /**
     * Parse buyer input into integer grams without floating point.
     *
     * @throws InvalidArgumentException
     */
    public static function parse(string|int $value, string $unit = 'g'): int
    {
        $text = trim((string) $value);

        if ($text === '') {
            throw new InvalidArgumentException('Enter a quantity.');
        }
        if (preg_match('/^\d+(\.\d+)?$/', $text) !== 1) {
            throw new InvalidArgumentException('Enter a valid positive number.');
        }

        return match ($unit) {
            'g' => str_contains($text, '.')
                ? throw new InvalidArgumentException('Grams must be a whole number.')
                : (int) $text,
            'kg' => self::kilogramsToGrams($text),
            default => throw new InvalidArgumentException('Unknown unit.'),
        };
    }

    /**
     * Below 1000 g show grams; at or above, show kg ("1.25 kg") or mixed ("1 kg 250 g").
     */
    public static function format(int $grams, bool $mixed = false): string
    {
        if ($grams < 1000) {
            return "{$grams} g";
        }

        $kilograms = intdiv($grams, 1000);
        $remainder = $grams % 1000;

        if ($remainder === 0) {
            return "{$kilograms} kg";
        }
        if ($mixed) {
            return "{$kilograms} kg {$remainder} g";
        }

        return $kilograms.'.'.rtrim(str_pad((string) $remainder, 3, '0', STR_PAD_LEFT), '0').' kg';
    }

    private static function kilogramsToGrams(string $text): int
    {
        [$whole, $fraction] = array_pad(explode('.', $text, 2), 2, '');

        if (strlen($fraction) > 3) {
            throw new InvalidArgumentException('Use at most 3 decimal places for kg.');
        }

        return (int) $whole * 1000 + (int) str_pad($fraction, 3, '0');
    }
}

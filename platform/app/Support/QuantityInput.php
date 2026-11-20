<?php

namespace App\Support;

use App\Models\ProductVariant;
use App\Services\Pricing\PriceResolver;
use InvalidArgumentException;

/**
 * Free-text quantities from bulk orders and staff quotes ("6kg", "500 g", "750", "4"), parsed into
 * base units without floats: grams for weight variants (a bare number means grams), whole units
 * otherwise.
 */
class QuantityInput
{
    /**
     * @throws InvalidArgumentException
     */
    public static function parse(?ProductVariant $variant, string $input): int
    {
        $text = strtolower(trim($input));

        if ($variant !== null && $variant->isWeight()) {
            if (preg_match('/^(\d+(?:\.\d+)?)\s*(kg|g)?$/', $text, $match) !== 1) {
                throw new InvalidArgumentException(__('Enter a weight like 500g or 2kg.'));
            }
            $grams = Weight::parse($match[1], $match[2] ?? 'g');
        } else {
            if (preg_match('/^\d+$/', $text) !== 1) {
                throw new InvalidArgumentException(__('Enter a whole quantity.'));
            }
            $grams = (int) $text;
        }

        if ($grams < 1 || $grams > 100_000_000) {
            throw new InvalidArgumentException(__('Enter a quantity greater than zero.'));
        }

        return $grams;
    }

    public static function label(?ProductVariant $variant, int $quantity): string
    {
        return $variant !== null ? PriceResolver::quantityLabel($variant, $quantity) : '× '.$quantity;
    }
}

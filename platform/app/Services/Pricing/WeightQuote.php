<?php

namespace App\Services\Pricing;

/**
 * Result of pricing a weight line: either a priced quote or a validation failure with suggestions.
 */
final readonly class WeightQuote
{
    /**
     * @param  list<int>  $suggestions  valid gram amounts to offer the buyer
     */
    private function __construct(
        public bool $ok,
        public int $grams,
        public ?int $perKgPaisa = null,
        public ?string $rateSource = null,
        public ?int $linePaisa = null,
        public ?string $errorCode = null,
        public ?string $error = null,
        public array $suggestions = [],
    ) {}

    public static function priced(int $grams, int $perKgPaisa, string $rateSource, int $linePaisa): self
    {
        return new self(true, $grams, $perKgPaisa, $rateSource, $linePaisa);
    }

    /**
     * @param  list<int>  $suggestions
     */
    public static function rejected(int $grams, string $errorCode, string $error, array $suggestions = []): self
    {
        return new self(false, $grams, errorCode: $errorCode, error: $error, suggestions: $suggestions);
    }
}

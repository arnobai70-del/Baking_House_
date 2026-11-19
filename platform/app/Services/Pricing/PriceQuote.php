<?php

namespace App\Services\Pricing;

/**
 * Server-authoritative price for one variant line, or a rejection with suggestions.
 */
final readonly class PriceQuote
{
    /**
     * @param  list<int>  $suggestions
     */
    private function __construct(
        public bool $ok,
        public int $variantId,
        public int $quantity,
        public ?int $unitPaisa = null,
        public ?int $perKgPaisa = null,
        public ?string $rateSource = null,
        public ?int $priceListId = null,
        public ?int $linePaisa = null,
        public ?int $standardLinePaisa = null,
        public ?string $errorCode = null,
        public ?string $error = null,
        public array $suggestions = [],
    ) {}

    public static function priced(int $variantId, int $quantity, ?int $unitPaisa, ?int $perKgPaisa, string $source, ?int $priceListId, int $linePaisa, int $standardLinePaisa): self
    {
        return new self(true, $variantId, $quantity, $unitPaisa, $perKgPaisa, $source, $priceListId, $linePaisa, $standardLinePaisa);
    }

    /**
     * @param  list<int>  $suggestions
     */
    public static function rejected(int $variantId, int $quantity, string $code, string $error, array $suggestions = []): self
    {
        return new self(false, $variantId, $quantity, errorCode: $code, error: $error, suggestions: $suggestions);
    }

    /**
     * Trade-negotiated sources never receive extra coupon discounts unless a promotion is stackable.
     */
    public function isTradePriced(): bool
    {
        return in_array($this->rateSource, ['contract', 'business-tier', 'reseller-tier'], true);
    }

    public function savingsPaisa(): int
    {
        return max(0, (int) $this->standardLinePaisa - (int) $this->linePaisa);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->ok
            ? [
                'ok' => true,
                'quantity' => $this->quantity,
                'unit_paisa' => $this->unitPaisa,
                'per_kg_paisa' => $this->perKgPaisa,
                'rate_source' => $this->rateSource,
                'line_paisa' => $this->linePaisa,
                'standard_line_paisa' => $this->standardLinePaisa,
                'savings_paisa' => $this->savingsPaisa(),
            ]
            : ['ok' => false, 'quantity' => $this->quantity, 'error_code' => $this->errorCode, 'error' => $this->error, 'suggestions' => $this->suggestions];
    }
}

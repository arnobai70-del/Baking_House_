<?php

namespace App\Services\Pricing;

use DateTimeImmutable;

/**
 * Admin-controlled weight pricing rule for one SKU/variant (pricing_mode = WEIGHT).
 */
final readonly class WeightRule
{
    /**
     * @param  array<string, list<array{fromGrams: int, perKgPaisa: int}>>  $tiers  keyed by role: retail|business|reseller
     * @param  array{perKgPaisa: int, startsAt?: ?DateTimeImmutable, endsAt?: ?DateTimeImmutable}|null  $campaign
     */
    public function __construct(
        public int $perKgPaisa,
        public int $minGrams = 1,
        public int $maxGrams = PHP_INT_MAX,
        public int $stepGrams = 1,
        public array $tiers = [],
        public ?array $campaign = null,
    ) {}
}

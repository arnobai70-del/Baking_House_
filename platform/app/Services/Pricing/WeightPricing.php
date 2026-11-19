<?php

namespace App\Services\Pricing;

use App\Support\Taka;
use App\Support\Weight;
use DateTimeImmutable;
use DateTimeInterface;

/**
 * Server-authoritative weight pricing engine (blueprint §05, docs/pricing-rules.md).
 *
 * line_paisa = round_half_up(grams × per_kg_paisa / 1000)
 * Precedence: contract → role tier → campaign → retail tier → standard.
 * Must stay behaviourally identical to prototype/assets/js/weight-pricing.js — both share one test matrix.
 */
class WeightPricing
{
    public function quote(
        WeightRule $rule,
        int $grams,
        string $role = 'retail',
        ?int $contractPerKgPaisa = null,
        ?int $stockGrams = null,
        ?DateTimeInterface $now = null,
    ): WeightQuote {
        $rejection = $this->validate($rule, $grams, $stockGrams);
        if ($rejection !== null) {
            return $rejection;
        }

        [$perKgPaisa, $source] = $this->resolveRate($rule, $grams, $role, $contractPerKgPaisa, $now ?? new DateTimeImmutable);

        return WeightQuote::priced($grams, $perKgPaisa, $source, Taka::divideRoundHalfUp($grams * $perKgPaisa, 1000));
    }

    /**
     * Validate quantity. Never changes the quantity — returns suggestions instead.
     */
    public function validate(WeightRule $rule, int $grams, ?int $stockGrams = null): ?WeightQuote
    {
        if ($grams <= 0) {
            return WeightQuote::rejected($grams, 'invalid', 'Quantity must be a positive whole number of grams.');
        }
        if ($grams < $rule->minGrams) {
            return WeightQuote::rejected($grams, 'below_min', 'Minimum order is '.Weight::format($rule->minGrams).'.', [$rule->minGrams]);
        }
        if ($grams > $rule->maxGrams) {
            return WeightQuote::rejected($grams, 'above_max', 'Maximum per order is '.Weight::format($rule->maxGrams).'.', [$rule->maxGrams]);
        }
        if ($grams % $rule->stepGrams !== 0) {
            $lower = intdiv($grams, $rule->stepGrams) * $rule->stepGrams;
            $suggestions = array_values(array_filter(
                [$lower, $lower + $rule->stepGrams],
                fn (int $candidate) => $candidate >= $rule->minGrams && $candidate <= $rule->maxGrams,
            ));

            return WeightQuote::rejected($grams, 'step', 'Quantity must be in steps of '.Weight::format($rule->stepGrams).'.', $suggestions);
        }
        if ($stockGrams !== null && $grams > $stockGrams) {
            $available = intdiv(max($stockGrams, 0), $rule->stepGrams) * $rule->stepGrams;

            return WeightQuote::rejected(
                $grams,
                'stock',
                'Only '.Weight::format(max($stockGrams, 0)).' in stock.',
                $available >= $rule->minGrams ? [$available] : [],
            );
        }

        return null;
    }

    /**
     * @return array{0: int, 1: string} [per_kg_paisa, source]
     */
    private function resolveRate(WeightRule $rule, int $grams, string $role, ?int $contractPerKgPaisa, DateTimeInterface $now): array
    {
        if ($contractPerKgPaisa !== null) {
            return [$contractPerKgPaisa, 'contract'];
        }

        if ($role !== 'retail' && ($tier = $this->pickTier($rule->tiers[$role] ?? [], $grams)) !== null) {
            return [$tier['perKgPaisa'], "{$role}-tier"];
        }

        $campaign = $rule->campaign;
        if ($campaign !== null
            && (($campaign['startsAt'] ?? null) === null || $campaign['startsAt'] <= $now)
            && (($campaign['endsAt'] ?? null) === null || $now < $campaign['endsAt'])) {
            return [$campaign['perKgPaisa'], 'campaign'];
        }

        if (($tier = $this->pickTier($rule->tiers['retail'] ?? [], $grams)) !== null) {
            return [$tier['perKgPaisa'], 'retail-tier'];
        }

        return [$rule->perKgPaisa, 'standard'];
    }

    /**
     * Highest threshold not exceeding the total grams.
     *
     * @param  list<array{fromGrams: int, perKgPaisa: int}>  $tiers
     * @return array{fromGrams: int, perKgPaisa: int}|null
     */
    private function pickTier(array $tiers, int $grams): ?array
    {
        $eligible = array_filter($tiers, fn (array $tier) => $grams >= $tier['fromGrams']);
        if ($eligible === []) {
            return null;
        }
        usort($eligible, fn (array $a, array $b) => $b['fromGrams'] <=> $a['fromGrams']);

        return $eligible[0];
    }
}

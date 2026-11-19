<?php

namespace Tests\Unit;

use App\Services\Pricing\WeightPricing;
use App\Services\Pricing\WeightRule;
use App\Support\Taka;
use App\Support\Weight;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Blueprint §05 critical test matrix. Mirrors tests/weight-pricing.test.mjs (prototype engine).
 */
class WeightPricingTest extends TestCase
{
    private WeightPricing $pricing;

    private WeightRule $chocolate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = new WeightPricing;
        $this->chocolate = new WeightRule(
            perKgPaisa: 65000,
            minGrams: 100,
            maxGrams: 25000,
            stepGrams: 50,
            tiers: ['business' => [['fromGrams' => 5000, 'perKgPaisa' => 60000]]],
        );
    }

    /**
     * @return array<string, array{int, int}>
     */
    public static function flatRateCases(): array
    {
        return [
            '100 g' => [100, 6500],
            '700 g' => [700, 45500],
            '900 g' => [900, 58500],
            '1000 g' => [1000, 65000],
            '1250 g' => [1250, 81250],
            '999 g' => [999, 64935],
        ];
    }

    #[DataProvider('flatRateCases')]
    public function test_flat_rate_at_650_per_kg(int $grams, int $expectedPaisa): void
    {
        $quote = $this->pricing->quote(new WeightRule(perKgPaisa: 65000), $grams);

        $this->assertTrue($quote->ok);
        $this->assertSame($expectedPaisa, $quote->linePaisa);
    }

    public function test_700_grams_formats_as_455_taka(): void
    {
        $quote = $this->pricing->quote(new WeightRule(perKgPaisa: 65000), 700);

        $this->assertSame('৳455', Taka::format($quote->linePaisa));
        $this->assertSame('৳812.50', Taka::format(81250));
    }

    public function test_label_switches_from_grams_to_kg_at_1000(): void
    {
        $this->assertSame('999 g', Weight::format(999));
        $this->assertSame('1 kg', Weight::format(1000));
        $this->assertSame('1.25 kg', Weight::format(1250));
        $this->assertSame('1 kg 250 g', Weight::format(1250, mixed: true));
        $this->assertSame('2.005 kg', Weight::format(2005));
    }

    public function test_below_minimum_is_rejected(): void
    {
        $rule = new WeightRule(perKgPaisa: 65000, minGrams: 250, stepGrams: 50);
        $quote = $this->pricing->quote($rule, 100);

        $this->assertFalse($quote->ok);
        $this->assertSame('below_min', $quote->errorCode);
        $this->assertSame([250], $quote->suggestions);
    }

    public function test_off_step_quantity_is_rejected_with_suggestions_not_changed(): void
    {
        $quote = $this->pricing->quote($this->chocolate, 725);

        $this->assertFalse($quote->ok);
        $this->assertSame('step', $quote->errorCode);
        $this->assertSame([700, 750], $quote->suggestions);
        $this->assertSame(725, $quote->grams);
    }

    public function test_insufficient_stock_is_rejected_with_available_suggestion(): void
    {
        $quote = $this->pricing->quote($this->chocolate, 700, stockGrams: 650);

        $this->assertFalse($quote->ok);
        $this->assertSame('stock', $quote->errorCode);
        $this->assertSame([650], $quote->suggestions);
    }

    public function test_business_tier_applies_from_5_kg_total(): void
    {
        $below = $this->pricing->quote($this->chocolate, 4950, role: 'business');
        $at = $this->pricing->quote($this->chocolate, 5000, role: 'business');

        $this->assertSame(65000, $below->perKgPaisa);
        $this->assertSame(60000, $at->perKgPaisa);
        $this->assertSame('business-tier', $at->rateSource);
        $this->assertSame(300000, $at->linePaisa);
    }

    public function test_retail_never_receives_business_tier(): void
    {
        $this->assertSame(65000, $this->pricing->quote($this->chocolate, 6000, role: 'retail')->perKgPaisa);
    }

    public function test_contract_price_wins_precedence(): void
    {
        $quote = $this->pricing->quote($this->chocolate, 6000, role: 'business', contractPerKgPaisa: 58000);

        $this->assertSame('contract', $quote->rateSource);
        $this->assertSame(58000, $quote->perKgPaisa);
    }

    public function test_expired_campaign_falls_back_to_standard(): void
    {
        $rule = new WeightRule(
            perKgPaisa: 65000,
            minGrams: 100,
            stepGrams: 50,
            campaign: ['perKgPaisa' => 55000, 'endsAt' => new DateTimeImmutable('2026-01-01T00:00:00Z')],
        );

        $expired = $this->pricing->quote($rule, 1000, now: new DateTimeImmutable('2026-11-20T00:00:00Z'));
        $active = $this->pricing->quote($rule, 1000, now: new DateTimeImmutable('2025-12-01T00:00:00Z'));

        $this->assertSame('standard', $expired->rateSource);
        $this->assertSame('campaign', $active->rateSource);
    }

    public function test_parse_handles_grams_and_kg_without_floats(): void
    {
        $this->assertSame(700, Weight::parse('700', 'g'));
        $this->assertSame(1250, Weight::parse('1.25', 'kg'));
        $this->assertSame(700, Weight::parse('0.7', 'kg'));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function invalidQuantities(): array
    {
        return [
            'too many kg decimals' => ['1.2345', 'kg'],
            'fractional grams' => ['70.5', 'g'],
            'negative' => ['-5', 'g'],
            'empty' => ['', 'g'],
            'unknown unit' => ['5', 'lb'],
        ];
    }

    #[DataProvider('invalidQuantities')]
    public function test_parse_rejects_invalid_input(string $value, string $unit): void
    {
        $this->expectException(\InvalidArgumentException::class);
        Weight::parse($value, $unit);
    }
}

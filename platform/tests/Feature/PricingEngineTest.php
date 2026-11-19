<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\ScheduledPriceChange;
use App\Services\Pricing\PriceResolver;
use App\Services\Pricing\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 4 pricing: price lists, volume tiers, customer-specific pricing, MOQ, scheduling.
 * Weight maths follows the blueprint §05 matrix (৳650/kg: 700 g = ৳455).
 */
class PricingEngineTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $chocolate;

    private ProductVariant $pan;

    private PriceResolver $prices;

    protected function setUp(): void
    {
        parent::setUp();
        $this->prices = app(PriceResolver::class);
        $this->chocolate = Product::factory()->withWeightVariant(65000, 50000, ['sku' => 'CHOC'])->create()->variants()->sole();
        $this->pan = Product::factory()->withFixedVariant(245000, 100, ['sku' => 'PAN'])->create()->variants()->sole();
    }

    private function business(): Organization
    {
        return Organization::factory()->business()->approved()->create();
    }

    public function test_blueprint_weight_matrix_at_standard_price(): void
    {
        $cases = [100 => 6500, 700 => 45500, 900 => 58500, 1000 => 65000, 1250 => 81250];

        foreach ($cases as $grams => $paisa) {
            $quote = $this->prices->quote($this->chocolate, $grams, PricingContext::retail());
            $this->assertTrue($quote->ok, "{$grams} g");
            $this->assertSame($paisa, $quote->linePaisa, "{$grams} g");
            $this->assertSame('standard', $quote->rateSource);
        }
    }

    public function test_weight_validation_matrix(): void
    {
        $retail = PricingContext::retail();

        $this->assertSame('below_min', $this->prices->quote($this->chocolate, 50, $retail)->errorCode);
        $step = $this->prices->quote($this->chocolate, 725, $retail);
        $this->assertSame('step', $step->errorCode);
        $this->assertSame([700, 750], $step->suggestions);
        $stock = $this->prices->quote($this->chocolate, 700, $retail, availableQuantity: 650);
        $this->assertSame('stock', $stock->errorCode);
        $this->assertSame([650], $stock->suggestions);
    }

    public function test_business_volume_tier_applies_on_total_grams_from_5_kg(): void
    {
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->chocolate, [[1, 63000], [5000, 60000]])->create();
        $context = PricingContext::forOrganization($this->business());

        $below = $this->prices->quote($this->chocolate, 4950, $context);
        $at = $this->prices->quote($this->chocolate, 5000, $context);

        $this->assertSame(63000, $below->perKgPaisa);
        $this->assertSame(60000, $at->perKgPaisa);
        $this->assertSame('business-tier', $at->rateSource);
        $this->assertSame(300000, $at->linePaisa);
        $this->assertSame(325000, $at->standardLinePaisa);
        $this->assertSame(25000, $at->savingsPaisa());
    }

    public function test_retail_never_receives_business_or_reseller_prices(): void
    {
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->chocolate, [[1, 50000]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($this->chocolate, [[1, 51000]])->create();

        $quote = $this->prices->quote($this->chocolate, 6000, PricingContext::retail());

        $this->assertSame(65000, $quote->perKgPaisa);
        $this->assertSame([], $this->prices->visibleTiers($this->chocolate, PricingContext::retail()));
    }

    public function test_reseller_does_not_get_business_prices_and_vice_versa(): void
    {
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->pan, [[1, 200000]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($this->pan, [[1, 210000]])->create();
        $reseller = Organization::factory()->reseller()->approved()->create();

        $this->assertSame(210000, $this->prices->quote($this->pan, 1, PricingContext::forOrganization($reseller))->unitPaisa);
        $this->assertSame(200000, $this->prices->quote($this->pan, 1, PricingContext::forOrganization($this->business()))->unitPaisa);
    }

    public function test_contract_price_wins_and_belongs_to_one_organization_only(): void
    {
        $mine = $this->business();
        $other = $this->business();
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->pan, [[1, 200000]])->create();
        PriceList::factory()->contractFor($mine)->withTiers($this->pan, [[1, 180000], [10, 170000]])->create();

        $this->assertSame(180000, $this->prices->quote($this->pan, 2, PricingContext::forOrganization($mine))->unitPaisa);
        $this->assertSame(170000, $this->prices->quote($this->pan, 10, PricingContext::forOrganization($mine))->unitPaisa);
        $this->assertSame('contract', $this->prices->quote($this->pan, 10, PricingContext::forOrganization($mine))->rateSource);
        $this->assertSame(200000, $this->prices->quote($this->pan, 2, PricingContext::forOrganization($other))->unitPaisa, 'Another org never sees my contract.');
    }

    public function test_campaign_list_is_time_bounded_and_beats_retail_volume_tier(): void
    {
        PriceList::factory()->audience(PriceListAudience::Retail)->withTiers($this->pan, [[5, 230000]])->create();
        PriceList::factory()->audience(PriceListAudience::Campaign)->withTiers($this->pan, [[1, 220000]])
            ->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(7)]);

        $this->assertSame(230000, $this->prices->quote($this->pan, 5, PricingContext::retail())->unitPaisa);
        $this->assertSame('retail-tier', $this->prices->quote($this->pan, 5, PricingContext::retail())->rateSource);

        $during = PricingContext::retail(at: now()->addDays(2));
        $this->assertSame(220000, $this->prices->quote($this->pan, 5, $during)->unitPaisa);

        $after = PricingContext::retail(at: now()->addDays(8));
        $this->assertSame(230000, $this->prices->quote($this->pan, 5, $after)->unitPaisa);
    }

    public function test_inactive_price_list_is_ignored(): void
    {
        PriceList::factory()->audience(PriceListAudience::Retail)->withTiers($this->pan, [[1, 1000]])->create(['is_active' => false]);

        $this->assertSame(245000, $this->prices->quote($this->pan, 1, PricingContext::retail())->unitPaisa);
    }

    public function test_minimum_order_quantity_for_unit_products(): void
    {
        $this->pan->forceFill(['min_order_quantity' => 6])->save();

        $quote = $this->prices->quote($this->pan->fresh(), 4, PricingContext::retail());

        $this->assertFalse($quote->ok);
        $this->assertSame('below_min', $quote->errorCode);
        $this->assertSame([6], $quote->suggestions);
        $this->assertTrue($this->prices->quote($this->pan->fresh(), 6, PricingContext::retail())->ok);
    }

    public function test_reserved_stock_reduces_what_can_be_priced(): void
    {
        $this->pan->forceFill(['reserved_quantity' => 98])->save();

        $quote = $this->prices->quote($this->pan->fresh(), 3, PricingContext::retail());

        $this->assertSame('stock', $quote->errorCode);
        $this->assertSame([2], $quote->suggestions);
    }

    public function test_quote_only_variants_cannot_be_priced(): void
    {
        $quoteOnly = ProductVariant::factory()->quoteOnly()->create(['stock_quantity' => 5]);

        $this->assertSame('quote_only', $this->prices->quote($quoteOnly, 1, PricingContext::retail())->errorCode);
    }

    public function test_scheduled_price_change_applies_once_with_audit(): void
    {
        ScheduledPriceChange::query()->create([
            'product_variant_id' => $this->chocolate->id, 'field' => 'per_kg_paisa', 'new_value_paisa' => 70000,
            'effective_at' => now()->addHour(), 'reason' => 'Supplier increase',
        ]);

        $this->artisan('pricing:apply-scheduled')->assertSuccessful();
        $this->assertSame(65000, $this->chocolate->fresh()->per_kg_paisa, 'Not yet due.');

        $this->travel(61)->minutes();
        $this->artisan('pricing:apply-scheduled')->expectsOutputToContain('Applied 1')->assertSuccessful();
        $this->artisan('pricing:apply-scheduled')->expectsOutputToContain('Applied 0')->assertSuccessful();

        $this->assertSame(70000, $this->chocolate->fresh()->per_kg_paisa);
        $this->assertSame(45500 * 70000 / 65000, $this->prices->quote($this->chocolate->fresh(), 700, PricingContext::retail())->linePaisa);
        $log = AuditLog::query()->where('action', 'product.price_changed')->sole();
        $this->assertSame(65000, $log->before['per_kg_paisa']);
        $this->assertSame('Scheduled: Supplier increase', $log->reason);
    }

    public function test_cancelled_scheduled_change_is_never_applied(): void
    {
        $change = ScheduledPriceChange::query()->create([
            'product_variant_id' => $this->pan->id, 'field' => 'price_paisa', 'new_value_paisa' => 1,
            'effective_at' => now()->subMinute(), 'reason' => 'Mistake',
        ]);
        $change->forceFill(['status' => ScheduledPriceChange::STATUS_CANCELLED])->save();

        $this->artisan('pricing:apply-scheduled');

        $this->assertSame(245000, $this->pan->fresh()->price_paisa);
    }
}

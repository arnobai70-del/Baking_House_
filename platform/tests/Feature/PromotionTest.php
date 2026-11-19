<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Bundle;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\User;
use App\Services\Pricing\PricingContext;
use App\Services\Pricing\QuoteService;
use App\Services\Promotions\PromotionEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Coupons, automatic promotions, bundles and the rules that stop unauthorised discount stacking.
 */
class PromotionTest extends TestCase
{
    use RefreshDatabase;

    private ProductVariant $pan;

    private ProductVariant $chocolate;

    private QuoteService $quotes;

    protected function setUp(): void
    {
        parent::setUp();
        $this->quotes = app(QuoteService::class);
        $this->pan = Product::factory()->withFixedVariant(100000, 50, ['sku' => 'PAN'])->create()->variants()->sole();
        $this->chocolate = Product::factory()->withWeightVariant(65000, 50000, ['sku' => 'CHOC'])->create()->variants()->sole();
    }

    /**
     * @return array<string, mixed>
     */
    private function quote(PricingContext $context, ?string $coupon = null, int $pans = 2, array $bundles = []): array
    {
        return $this->quotes->quote([['sku' => 'PAN', 'quantity' => $pans]], $bundles, $context, $coupon);
    }

    public function test_percentage_coupon_with_cap(): void
    {
        Promotion::factory()->coupon('SAVE10')->percent(1000)->create(['max_discount_paisa' => 15000]);

        $result = $this->quote(PricingContext::retail(), 'save10');

        $this->assertSame(200000, $result['subtotal_paisa']);
        $this->assertSame(15000, $result['discount_paisa'], '10% would be ৳200 but the cap is ৳150.');
        $this->assertSame(185000, $result['total_paisa']);
        $this->assertTrue($result['coupon']['applied']);
    }

    public function test_invalid_expired_and_wrong_audience_coupons_are_explained(): void
    {
        Promotion::factory()->coupon('OLD')->create(['ends_at' => now()->subDay()]);
        Promotion::factory()->coupon('TRADEONLY')->create(['audiences' => ['business']]);

        $this->assertSame('This coupon code is not valid.', $this->quote(PricingContext::retail(), 'NOPE')['coupon']['message']);
        $this->assertSame('This coupon is not active or has expired.', $this->quote(PricingContext::retail(), 'OLD')['coupon']['message']);
        $this->assertSame('This coupon is not available for your account type.', $this->quote(PricingContext::retail(), 'TRADEONLY')['coupon']['message']);
        $this->assertSame(0, $this->quote(PricingContext::retail(), 'TRADEONLY')['discount_paisa']);
    }

    public function test_minimum_subtotal_is_enforced(): void
    {
        Promotion::factory()->coupon('BIG')->fixed(20000)->create(['min_subtotal_paisa' => 500000]);

        $result = $this->quote(PricingContext::retail(), 'BIG');

        $this->assertSame(0, $result['discount_paisa']);
        $this->assertStringContainsString('Spend at least ৳5,000', $result['coupon']['message']);
    }

    public function test_only_the_best_non_stackable_promotion_applies(): void
    {
        Promotion::factory()->percent(500)->create(['name' => 'Auto 5%']);
        Promotion::factory()->coupon('TWENTY')->percent(2000)->create();

        $result = $this->quote(PricingContext::retail(), 'TWENTY');

        $this->assertCount(1, $result['promotions']);
        $this->assertSame(40000, $result['discount_paisa']);
        $this->assertSame('TWENTY', $result['promotions'][0]['code']);
    }

    public function test_weaker_coupon_is_refused_when_a_better_automatic_offer_applies(): void
    {
        Promotion::factory()->percent(2000)->create(['name' => 'Auto 20%']);
        Promotion::factory()->coupon('FIVE')->percent(500)->create();

        $result = $this->quote(PricingContext::retail(), 'FIVE');

        $this->assertSame(40000, $result['discount_paisa']);
        $this->assertFalse($result['coupon']['applied']);
        $this->assertStringContainsString('cannot be combined', $result['coupon']['message']);
    }

    public function test_stackable_promotions_add_up_but_never_exceed_the_subtotal(): void
    {
        Promotion::factory()->percent(1000)->create(['stackable' => true]);
        Promotion::factory()->coupon('MEGA')->fixed(500000)->create();

        $result = $this->quote(PricingContext::retail(), 'MEGA');

        $this->assertSame(200000, $result['discount_paisa']);
        $this->assertSame(0, $result['total_paisa']);
    }

    public function test_coupons_do_not_stack_on_business_contract_prices(): void
    {
        $organization = Organization::factory()->business()->approved()->create();
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->pan, [[1, 80000]])->create();
        Promotion::factory()->coupon('ALL10')->percent(1000)->create();
        $context = PricingContext::forOrganization($organization);

        $result = $this->quote($context, 'ALL10');

        $this->assertSame(160000, $result['subtotal_paisa']);
        $this->assertSame(0, $result['discount_paisa'], 'Trade-priced lines are excluded from non-stackable coupons.');
        $this->assertSame('This coupon does not apply to the items in your cart.', $result['coupon']['message']);
    }

    public function test_owner_marked_stackable_coupon_may_apply_on_trade_prices(): void
    {
        $organization = Organization::factory()->business()->approved()->create();
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->pan, [[1, 80000]])->create();
        Promotion::factory()->coupon('TRADE5')->percent(500)->create(['stackable' => true, 'audiences' => ['business']]);

        $this->assertSame(8000, $this->quote(PricingContext::forOrganization($organization), 'TRADE5')['discount_paisa']);
    }

    public function test_product_scoped_promotion_only_discounts_its_products(): void
    {
        $promotion = Promotion::factory()->coupon('CHOC20')->percent(2000)->create(['scope' => Promotion::SCOPE_PRODUCTS]);
        $promotion->products()->attach($this->chocolate->product_id);

        $result = $this->quotes->quote([['sku' => 'PAN', 'quantity' => 1], ['sku' => 'CHOC', 'quantity' => 1000]], [], PricingContext::retail(), 'CHOC20');

        $this->assertSame(13000, $result['discount_paisa'], '20% of ৳650 chocolate only.');
    }

    public function test_bundle_price_uses_standard_child_prices_and_blocks_coupon_stacking(): void
    {
        $bundle = Bundle::factory()->withItems([[$this->pan, 1], [$this->chocolate, 1000]])->create(['slug' => 'starter', 'discount_value' => 1000]);
        Promotion::factory()->coupon('EXTRA')->percent(1000)->create();

        $result = $this->quotes->quote([], [['slug' => 'starter', 'quantity' => 2]], PricingContext::retail(), 'EXTRA');
        $line = $result['lines'][0];

        $this->assertSame(2 * (100000 + 65000), $line['normal_paisa']);
        $this->assertSame(33000, $line['discount_paisa']);
        $this->assertSame(297000, $result['subtotal_paisa']);
        $this->assertSame(0, $result['discount_paisa'], 'Bundle discount never stacks with a coupon.');
        $this->assertSame($bundle->id, Bundle::query()->where('slug', 'starter')->value('id'));
    }

    public function test_bundle_checks_child_stock_and_audience(): void
    {
        Bundle::factory()->withItems([[$this->pan, 30]])->create(['slug' => 'big']);
        Bundle::factory()->withItems([[$this->pan, 1]])->create(['slug' => 'trade', 'audiences' => ['business']]);

        $this->assertStringContainsString('Not enough stock', $this->quotes->quote([], [['slug' => 'big', 'quantity' => 2]], PricingContext::retail())['lines'][0]['error']);
        $this->assertSame('This bundle is not available for your account type.', $this->quotes->quote([], [['slug' => 'trade', 'quantity' => 1]], PricingContext::retail())['lines'][0]['error']);
    }

    public function test_usage_limit_is_atomic_and_redemption_is_idempotent_per_order(): void
    {
        $promotion = Promotion::factory()->coupon('ONCE')->create(['usage_limit' => 1]);
        $engine = app(PromotionEngine::class);

        $engine->redeem($promotion, 'order-1', 1000, null);
        $engine->redeem($promotion, 'order-1', 1000, null);
        $this->assertSame(1, $promotion->fresh()->used_count, 'Same order counted once.');

        $this->expectException(RuntimeException::class);
        $engine->redeem($promotion, 'order-2', 1000, null);
    }

    public function test_per_customer_limit_requires_sign_in_and_is_enforced(): void
    {
        $promotion = Promotion::factory()->coupon('WELCOME')->create(['per_customer_limit' => 1]);
        $user = User::factory()->create();

        $this->assertSame('Sign in to use this coupon.', $this->quote(PricingContext::retail(), 'WELCOME')['coupon']['message']);
        $this->assertTrue($this->quote(PricingContext::retail($user->id), 'WELCOME')['coupon']['applied']);

        app(PromotionEngine::class)->redeem($promotion, 'order-9', 1000, $user->id);

        $this->assertStringContainsString('maximum number of times', $this->quote(PricingContext::retail($user->id), 'WELCOME')['coupon']['message']);
    }

    public function test_scheduled_promotion_window(): void
    {
        Promotion::factory()->percent(1000)->create(['starts_at' => now()->addDay(), 'ends_at' => now()->addDays(3)]);

        $this->assertSame(0, $this->quote(PricingContext::retail())['discount_paisa']);
        $this->assertSame(20000, $this->quote(PricingContext::retail(at: now()->addDays(2)))['discount_paisa']);
        $this->assertSame(0, $this->quote(PricingContext::retail(at: now()->addDays(4)))['discount_paisa']);
    }
}

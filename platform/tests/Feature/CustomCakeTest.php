<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingMode;
use App\Enums\SellingUnit;
use App\Models\CakeBlackoutDate;
use App\Models\CakeDesign;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\Setting;
use App\Models\User;
use App\Services\Cakes\CakePricer;
use App\Services\Cakes\CakeSchedule;
use App\Services\Cakes\CakeSettings;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Orders\PlacedOrder;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Custom cakes, Part 1: server pricing, dates / capacity, and the cake travelling through the normal
 * cart → checkout → order → payment pipeline with an advance. All prices are illustrative test data.
 */
class CustomCakeTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    private CakeProduct $cake;

    protected function setUp(): void
    {
        parent::setUp();
        // Tuesday 24 Nov 2026, 09:00 in Dhaka (UTC+6).
        $this->travelTo(Carbon::parse('2026-11-24 03:00:00', 'UTC'));
        $this->zones();
        $this->cake = $this->cakeProduct();
        app(CakeSettings::class)->save(['cake.orders_open' => true, 'cake.daily_capacity' => 2]);
    }

    public function test_price_is_built_line_by_line_from_admin_options(): void
    {
        $priced = $this->price(['grams' => 1500, 'flavour' => 'chocolate', 'shape' => 'heart', 'filling' => 'ganache', 'message' => 'Happy Birthday']);

        $this->assertTrue($priced['ok'], (string) $priced['error']);
        $this->assertSame([210000, 25000, 22500, 5000], array_column($priced['breakdown'], 'paisa'));
        $this->assertSame(262500, $priced['line_paisa']);
        $this->assertSame('standard', $priced['complexity']);
        $this->assertTrue($priced['uses_test_prices']);
    }

    public function test_invalid_or_disabled_choices_are_refused(): void
    {
        $this->assertFalse($this->price(['grams' => 700])['ok'], 'Weight must be one of the configured steps.');
        $this->assertFalse($this->price(['flavour' => 'mango'])['ok'], 'Unknown flavour.');
        $this->assertFalse($this->price(['diets' => ['eggless']])['ok'], 'Diets are off by default.');

        $this->cake->forceFill(['allow_eggless' => true, 'allow_sugar_free' => true])->save();
        $this->assertTrue($this->price(['diets' => ['eggless']])['ok']);
        $this->assertFalse($this->price(['diets' => ['eggless', 'sugar_free']])['ok'], 'Both together only when allowed.');
    }

    public function test_tiers_make_a_cake_complex_and_three_tiers_need_a_quotation(): void
    {
        $this->assertSame('complex', $this->price(['tiers' => 2])['complexity']);

        $three = $this->price(['tiers' => 3]);
        $this->assertFalse($three['ok']);
        $this->assertTrue($three['requires_quote']);
    }

    public function test_test_prices_are_blocked_outside_local_and_testing(): void
    {
        $this->app['env'] = 'production';
        $priced = $this->price();

        $this->assertFalse($priced['ok']);
        $this->assertNotNull($priced['error']);
    }

    public function test_dates_respect_lead_time_blackouts_capacity_and_closing(): void
    {
        $schedule = app(CakeSchedule::class);

        $first = $schedule->availableDates($this->cake, 'standard')[0];
        $this->assertSame('2026-11-26', $first['date'], '48 h lead time from Tuesday 09:00.');
        $complexFirst = $schedule->availableDates($this->cake, 'complex')[0];
        $this->assertSame(['2026-11-27', ['10:00-13:00', '14:00-17:00', '18:00-21:00']], [$complexFirst['date'], $complexFirst['slots']], '72 h from Tue 09:00 is Fri 09:00.');
        $this->travelTo(Carbon::parse('2026-11-24 05:00:00', 'UTC')); // 11:00 in Dhaka
        $this->assertSame(['14:00-17:00', '18:00-21:00'], $schedule->availableDates($this->cake, 'standard')[0]['slots'], 'Thu 10:00 is now under 48 h away.');
        $this->travelTo(Carbon::parse('2026-11-24 03:00:00', 'UTC'));
        $this->assertNotNull($schedule->check($this->cake, 'standard', '2026-11-25', '14:00-17:00'));
        $this->assertNull($schedule->check($this->cake, 'standard', '2026-11-26', '14:00–17:00'), 'En dash and hyphen are the same slot.');
        $this->assertNotNull($schedule->check($this->cake, 'standard', '2026-11-26', '23:00-24:00'), 'Unknown slot.');

        CakeBlackoutDate::query()->create(['date' => '2026-11-27', 'kind' => CakeBlackoutDate::CLOSED, 'reason' => 'Holiday']);
        $this->assertNotNull($schedule->check($this->cake, 'standard', '2026-11-27', '14:00-17:00'));
        $this->assertNotContains('2026-11-27', array_column($schedule->availableDates($this->cake, 'standard'), 'date'));

        CakeBlackoutDate::query()->create(['date' => '2026-11-25', 'kind' => CakeBlackoutDate::URGENT_OPEN]);
        $this->assertNull($schedule->check($this->cake, 'standard', '2026-11-25', '14:00-17:00'), 'Admin accepted urgent orders for tomorrow.');

        $this->placeCake('2026-11-26');
        $this->placeCake('2026-11-26');
        $this->assertSame(0, $schedule->remaining('2026-11-26'));
        $this->assertNotNull($schedule->check($this->cake, 'standard', '2026-11-26', '18:00-21:00'), 'Daily capacity reached.');

        app(CakeSettings::class)->save(['cake.orders_open' => false]);
        $this->assertSame([], $schedule->availableDates($this->cake, 'standard'));
    }

    public function test_no_dates_are_offered_until_capacity_is_configured(): void
    {
        app(CakeSettings::class)->save(['cake.daily_capacity' => null]);

        $this->assertFalse(app(CakeSettings::class)->ordersOpen());
        $this->assertSame([], app(CakeSchedule::class)->availableDates($this->cake, 'standard'));
    }

    public function test_guest_designs_a_cake_and_it_lands_in_the_normal_cart(): void
    {
        $this->get('/custom-cake')->assertOk()->assertSee('Design your custom cake')->assertSee('TEST prices');

        $this->postJson('/custom-cake/price', $this->form(['grams' => 1500, 'flavour' => 'chocolate']))
            ->assertOk()->assertJsonPath('ok', true)->assertJsonPath('total', '৳2,100');

        $response = $this->post('/custom-cake', $this->form(['grams' => 1500, 'flavour' => 'chocolate']));
        $response->assertRedirect(route('cart.show'));

        $design = CakeDesign::query()->sole();
        $this->assertSame(CakeDesign::IN_CART, $design->status);
        $this->assertSame('14:00-17:00', $design->time_slot);
        $cart = Cart::query()->sole();
        $this->assertSame($design->id, $cart->items()->sole()->cake_design_id);

        $this->withCookies([CartService::COOKIE => $cart->token])->get('/cart')->assertOk()->assertSee('1.5 kg')->assertSee('Edit design');

        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        $this->get('/bn/custom-cake')->assertOk()->assertSee('<html lang="bn">', false)->assertSee('আপনার কাস্টম কেক ডিজাইন করুন');
    }

    public function test_a_guest_cannot_open_someone_elses_design(): void
    {
        $this->post('/custom-cake', $this->form());
        $design = CakeDesign::query()->sole();

        $this->get('/custom-cake?design='.$design->uuid)->assertOk()->assertDontSee($design->uuid);
    }

    public function test_store_rejects_a_date_that_is_not_bookable(): void
    {
        $this->post('/custom-cake', $this->form(['date' => '2026-11-25']))->assertSessionHasErrors('date');
        $this->assertSame(0, CakeDesign::query()->count());
    }

    public function test_cake_order_takes_an_advance_and_the_balance_is_collected_later(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $cart = $this->cakeCart(['grams' => 1500, 'flavour' => 'chocolate']);

        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Dhaka', PaymentMethod::Bkash);
        $this->assertSame([], $summary['errors']);
        $this->assertNotContains(PaymentMethod::CashOnDelivery, $summary['methods'], 'An advance is due now.');
        $this->assertSame(15000, $summary['delivery_paisa'], 'Cake delivery charge of the zone.');
        $this->assertSame([225000, 50, 112500, 112500], [$summary['total_paisa'], $summary['advance_percent'], $summary['pay_now_paisa'], $summary['balance_paisa']]);

        $order = $this->placeCart($cart, PaymentMethod::Bkash)->order;
        $item = $order->items()->sole();
        $this->assertSame('cake', $item->type);
        $this->assertSame('2026-11-26', $item->scheduled_for->format('Y-m-d'));
        $this->assertSame(112500, $order->advance_paisa);
        $this->assertSame(112500, $this->payment($order)->amount_paisa);
        $this->assertSame(CakeDesign::ORDERED, CakeDesign::query()->sole()->status);

        $this->sendCallback($this->payment($order))->assertOk();
        $order->refresh();
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->payment_status);
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(112500, $order->balancePaisa());

        app(PaymentService::class)->collectBalance($order, PaymentMethod::CashOnDelivery, null, User::factory()->admin()->create());
        $order->refresh();
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(0, $order->balancePaisa());
    }

    public function test_pickup_needs_no_delivery_zone_and_has_no_delivery_charge(): void
    {
        $location = PickupLocation::query()->create(['name' => 'Main kitchen', 'address' => 'Road 1', 'is_active' => true]);
        $cart = $this->cakeCart();

        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), null, PaymentMethod::Bkash, 'pickup', $location->id);
        $this->assertSame([], $summary['errors']);
        $this->assertSame(0, $summary['delivery_paisa']);

        $order = $this->placeCart($cart, PaymentMethod::Bkash, 'pickup', $location->id)->order;
        $this->assertTrue($order->isPickup());
        $this->assertSame($location->id, $order->pickup_location_id);
    }

    public function test_zone_without_cake_delivery_or_with_tiered_cake_is_refused(): void
    {
        $cart = $this->cakeCart();
        $outside = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Khulna', PaymentMethod::Bkash);
        $this->assertNotEmpty($outside['errors'], 'Outside Dhaka has no cake delivery.');

        $tiered = $this->cakeCart(['tiers' => 2, 'date' => '2026-11-28']);
        $this->assertNotEmpty(app(CheckoutService::class)->summarize($tiered, PricingContext::retail(), 'Dhaka', PaymentMethod::Bkash)['errors']);
    }

    public function test_cash_on_delivery_is_allowed_only_without_an_advance(): void
    {
        app(CakeSettings::class)->save(['cake.advance_percent' => 0]);
        $cart = $this->cakeCart();

        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Dhaka', PaymentMethod::CashOnDelivery);
        $this->assertContains(PaymentMethod::CashOnDelivery, $summary['methods']);
        $this->assertSame($summary['total_paisa'], $summary['pay_now_paisa']);

        app(CakeSettings::class)->save(['cake.cod_enabled' => false]);
        $this->assertNotContains(PaymentMethod::CashOnDelivery, app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Dhaka')['methods']);
    }

    public function test_full_payment_cakes_take_the_whole_total_now(): void
    {
        $this->cake->forceFill(['full_payment_required' => true])->save();
        $summary = app(CheckoutService::class)->summarize($this->cakeCart(), PricingContext::retail(), 'Dhaka', PaymentMethod::Bkash);

        $this->assertSame(100, $summary['advance_percent']);
        $this->assertSame($summary['total_paisa'], $summary['pay_now_paisa']);
        $this->assertSame(0, $summary['balance_paisa']);
    }

    public function test_cake_quantity_cannot_be_changed_and_no_stock_is_reserved(): void
    {
        $cart = $this->cakeCart();
        $item = $cart->items()->sole();

        $this->expectException(ValidationException::class);
        try {
            app(CartService::class)->updateQuantity($item, 3, PricingContext::retail());
        } finally {
            $this->placeCart($cart, PaymentMethod::Bkash);
            $this->assertSame(0, $this->cake->product->variants()->sole()->fresh()->reserved_quantity);
        }
    }

    public function test_the_quote_only_cake_variant_cannot_be_added_through_normal_add_to_cart(): void
    {
        $variant = $this->cake->product->variants()->sole();
        $cart = Cart::query()->create(['token' => Str::random(40)]);

        $this->expectException(ValidationException::class);
        try {
            app(CartService::class)->addVariant($cart, $variant, 1, PricingContext::retail());
        } finally {
            $this->assertSame(0, $cart->items()->count(), 'An unpriced cake can only be bought through the designer.');
        }
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function price(array $overrides = []): array
    {
        $spec = array_merge($this->spec(), $overrides);

        return app(CakePricer::class)->price($this->cake->product->variants()->sole()->load('product.cakeProfile'), $spec);
    }

    /**
     * @return array<string, mixed>
     */
    private function spec(): array
    {
        return ['shape' => 'round', 'grams' => 1000, 'tiers' => 1, 'flavour' => 'vanilla', 'filling' => '', 'cream' => '', 'diets' => [], 'message' => '', 'print' => false, 'extras' => [], 'note' => ''];
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return array_merge($this->spec(), ['cake' => $this->cake->id, 'date' => '2026-11-26', 'slot' => '14:00-17:00'], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function cakeCart(array $overrides = []): Cart
    {
        $form = $this->form($overrides);
        $cart = Cart::query()->create(['token' => Str::random(40)]);
        $design = CakeDesign::query()->create([
            'uuid' => (string) Str::uuid(), 'owner_token_hash' => hash('sha256', $cart->token),
            'product_variant_id' => $this->cake->product->variants()->sole()->id,
            'spec' => array_intersect_key($form, $this->spec()), 'scheduled_for' => $form['date'], 'time_slot' => $form['slot'], 'status' => CakeDesign::DRAFT,
        ]);
        app(CartService::class)->addCakeDesign($cart, $design);

        return $cart;
    }

    private function placeCart(Cart $cart, PaymentMethod $method, string $fulfilment = 'delivery', ?int $pickupId = null): PlacedOrder
    {
        $district = $fulfilment === 'pickup' ? '' : 'Dhaka';
        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), $district ?: null, $method, $fulfilment, $pickupId);
        $details = new CheckoutDetails('Rahim Uddin', '01712345678', null, $fulfilment === 'pickup' ? '' : 'House 1', $fulfilment === 'pickup' ? '' : 'Mirpur', $district, null, $method, Str::random(40), $summary['fingerprint'], $fulfilment, $pickupId);

        return app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);
    }

    private function placeCake(string $date): Order
    {
        return $this->placeCart($this->cakeCart(['date' => $date]), PaymentMethod::Bkash)->order;
    }

    private function cakeProduct(): CakeProduct
    {
        $product = Product::factory()->create(['slug' => 'custom-cake', 'name' => 'Custom Cake']);
        $product->variants()->delete();
        $product->variants()->create(['sku' => 'CAKE-1', 'name' => 'Custom cake', 'pricing_mode' => PricingMode::QuoteOnly, 'selling_unit' => SellingUnit::Piece, 'stock_quantity' => 0, 'is_active' => true]);
        $cake = CakeProduct::query()->create(['product_id' => $product->id, 'min_grams' => 500, 'max_grams' => 3000, 'step_grams' => 500, 'max_tiers' => 3]);

        $options = [
            ['flavour', 'vanilla', 'per_kg', 1200, 'simple', false], ['flavour', 'chocolate', 'per_kg', 1400, 'simple', false],
            ['shape', 'round', 'flat', 0, 'simple', false], ['shape', 'heart', 'flat', 250, 'standard', false],
            ['tier', '2', 'flat', 1500, 'complex', false], ['tier', '3', 'flat', 3000, 'complex', true],
            ['filling', 'ganache', 'per_kg', 150, 'standard', false], ['message', 'standard', 'flat', 50, 'simple', false],
            ['diet', 'eggless', 'per_kg', 150, 'standard', false], ['diet', 'sugar_free', 'per_kg', 200, 'standard', false],
        ];
        foreach ($options as $i => [$group, $code, $type, $taka, $complexity, $quote]) {
            CakeOption::query()->create(['group' => $group, 'code' => $code, 'name' => Str::headline($code), 'price_type' => $type, 'price_paisa' => $taka * 100,
                'is_test' => true, 'requires_quote' => $quote, 'complexity' => $complexity, 'is_active' => true, 'sort_order' => $i]);
        }
        DeliveryZone::forDistrict('Dhaka')->forceFill(['cake_delivery_enabled' => true, 'cake_charge_paisa' => 15000])->save();

        return $cake;
    }
}

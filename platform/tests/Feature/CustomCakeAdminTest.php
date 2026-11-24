<?php

namespace Tests\Feature;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PricingMode;
use App\Enums\SellingUnit;
use App\Models\AuditLog;
use App\Models\CakeBlackoutDate;
use App\Models\CakeDesign;
use App\Models\CakeOption;
use App\Models\CakeProduct;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\PickupLocation;
use App\Models\Product;
use App\Models\User;
use App\Services\Cakes\CakeSettings;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Custom cakes, admin side (Part 1): settings, options with owner price approval, cake products,
 * calendar, pickup, zone cake rules, the balance action on orders, and the readiness gate.
 */
class CustomCakeAdminTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    private User $admin;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(Carbon::parse('2026-11-24 03:00:00', 'UTC'));
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->zones();
        $this->admin = User::factory()->admin()->create();
        $this->owner = User::factory()->superAdmin()->create();
    }

    public function test_every_cake_admin_page_renders_for_staff_and_is_closed_to_customers(): void
    {
        $cake = $this->cake();
        $pages = ['/admin/cakes/settings', '/admin/cakes/options', '/admin/cakes/products', '/admin/cakes/products/'.$cake->id,
            '/admin/cakes/simulator', '/admin/cakes/simulator?cake='.$cake->id.'&grams=1000&flavour=vanilla&shape=round', '/admin/cakes/calendar', '/admin/cakes/pickup'];
        foreach ($pages as $page) {
            $this->staff($this->admin)->get($page)->assertOk();
        }
        $this->staff($this->admin)->get('/admin/cakes/simulator?cake='.$cake->id.'&grams=1000&flavour=vanilla&shape=round')->assertSee('৳1,200')->assertSee('Uses TEST prices');

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/cakes/settings')->assertForbidden();
    }

    public function test_settings_are_saved_validated_and_audited(): void
    {
        $this->staff($this->admin)->put('/admin/cakes/settings', $this->settingsForm(['time_slots' => "10:00–13:00\nevening"]))->assertSessionHasErrors('time_slots');

        $this->staff($this->admin)->put('/admin/cakes/settings', $this->settingsForm())->assertSessionHasNoErrors();
        $settings = app(CakeSettings::class);
        $this->assertTrue($settings->ordersOpen());
        $this->assertSame(6, $settings->capacity());
        $this->assertSame(['10:00-13:00', '15:00-18:00'], $settings->timeSlots());
        $this->assertSame(30, $settings->advancePercentFor(100000));
        $this->assertSame(100, $settings->advancePercentFor(1000000), 'High-value orders pay more up front.');
        $this->assertSame(20000, $settings->rushFeeFor(30));
        $this->assertTrue(AuditLog::query()->where('action', 'cake.settings_updated')->exists());

        $this->staff($this->admin)->put('/admin/cakes/settings', $this->settingsForm(['daily_capacity' => '']))->assertSessionHasNoErrors();
        $this->assertFalse($settings->ordersOpen(), 'No capacity, no orders.');
    }

    public function test_new_and_repriced_options_are_test_prices_until_the_owner_approves_them(): void
    {
        $this->staff($this->admin)->post('/admin/cakes/options', $this->optionForm())->assertSessionHasNoErrors();
        $option = CakeOption::query()->sole();
        $this->assertTrue($option->is_test);
        $this->assertSame(130000, $option->price_paisa);

        $this->staff($this->admin)->post('/admin/cakes/options', $this->optionForm())->assertSessionHasErrors('code');

        $this->staff($this->admin)->post('/admin/cakes/options/approve', ['options' => [$option->id]])->assertForbidden();
        $this->staff($this->owner)->post('/admin/cakes/options/approve', ['options' => [$option->id]])->assertSessionHasNoErrors();
        $this->assertFalse($option->fresh()->is_test);
        $this->assertTrue(AuditLog::query()->where('action', 'cake.prices_approved')->exists());

        $this->staff($this->admin)->put('/admin/cakes/options/'.$option->id, $this->optionForm(['name' => 'Vanilla bean']))->assertSessionHasNoErrors();
        $this->assertFalse($option->fresh()->is_test, 'Renaming keeps the approval.');
        $this->staff($this->admin)->put('/admin/cakes/options/'.$option->id, $this->optionForm(['price' => '1,350']))->assertSessionHasNoErrors();
        $this->assertTrue($option->fresh()->is_test, 'A new price needs approval again.');
        $this->assertSame(135000, $option->fresh()->price_paisa);
    }

    public function test_only_quote_only_products_can_become_designable_and_rules_are_saved(): void
    {
        $fixed = Product::factory()->withFixedVariant()->create();
        $this->staff($this->admin)->post('/admin/cakes/products', ['product_id' => $fixed->id])->assertSessionHasErrors('product_id');

        $product = $this->quoteOnlyProduct();
        $this->staff($this->admin)->post('/admin/cakes/products', ['product_id' => $product->id])->assertSessionHasNoErrors();
        $cake = CakeProduct::query()->sole();

        $this->staff($this->admin)->put('/admin/cakes/products/'.$cake->id, [
            'min_grams' => 1000, 'max_grams' => 500, 'step_grams' => 500, 'max_tiers' => 1,
        ])->assertSessionHasErrors('max_grams');
        $this->staff($this->admin)->put('/admin/cakes/products/'.$cake->id, [
            'min_grams' => 1000, 'max_grams' => 4000, 'step_grams' => 1000, 'max_tiers' => 2, 'lead_time_hours' => 36, 'advance_percent' => 60,
            'full_payment_required' => 0, 'allow_eggless' => 1, 'allow_sugar_free' => 0, 'allow_both_diets' => 0, 'allergens' => 'Gluten, milk',
        ])->assertSessionHasNoErrors();

        $cake->refresh();
        $this->assertSame([1000, 2000, 3000, 4000], $cake->weightChoices());
        $this->assertSame([36, 60, true, false], [$cake->lead_time_hours, $cake->advance_percent, $cake->allow_eggless, $cake->allow_sugar_free]);
    }

    public function test_calendar_closes_and_reopens_dates_and_pickup_locations_are_managed(): void
    {
        $this->staff($this->admin)->post('/admin/cakes/calendar', ['date' => '2026-11-20', 'kind' => 'closed'])->assertSessionHasErrors('date');
        $this->staff($this->admin)->post('/admin/cakes/calendar', ['date' => '2026-12-16', 'kind' => 'closed', 'reason' => 'Victory Day'])->assertSessionHasNoErrors();
        $exception = CakeBlackoutDate::query()->sole();
        $this->assertSame(CakeBlackoutDate::CLOSED, $exception->kind);
        $this->staff($this->admin)->get('/admin/cakes/calendar')->assertOk()->assertSee('Victory Day');
        $this->staff($this->admin)->delete('/admin/cakes/calendar/'.$exception->id)->assertSessionHasNoErrors();
        $this->assertSame(0, CakeBlackoutDate::query()->count());

        $this->staff($this->admin)->post('/admin/cakes/pickup', ['name' => 'Mirpur shop', 'address' => 'Road 10, Mirpur', 'is_active' => 1])->assertSessionHasNoErrors();
        $location = PickupLocation::query()->sole();
        $this->staff($this->admin)->put('/admin/cakes/pickup/'.$location->id, ['name' => 'Mirpur shop', 'address' => 'Road 10, Mirpur', 'is_active' => 0])->assertSessionHasNoErrors();
        $this->assertFalse($location->fresh()->is_active);
    }

    public function test_zone_form_saves_cake_delivery_rules(): void
    {
        $zone = DeliveryZone::forDistrict('Dhaka');
        $this->staff($this->admin)->put('/admin/zones/'.$zone->id, [
            'name' => $zone->name, 'districts' => ['Dhaka'], 'charge' => '80', 'is_active' => 1, 'cod_available' => 1,
            'cake_delivery_enabled' => 1, 'cake_charge' => '150', 'cake_free_over' => '5000', 'cake_min_order' => '1000', 'cake_tiered_allowed' => 0,
        ])->assertSessionHasNoErrors();

        $zone->refresh();
        $this->assertSame([true, 15000, 500000, 100000, false], [$zone->cake_delivery_enabled, $zone->cake_charge_paisa, $zone->cake_free_over_paisa, $zone->cake_min_order_paisa, $zone->cake_tiered_allowed]);
    }

    public function test_staff_record_the_balance_and_can_refund_an_advance(): void
    {
        $cake = $this->cake();
        app(CakeSettings::class)->save(['cake.orders_open' => true, 'cake.daily_capacity' => 5]);
        DeliveryZone::forDistrict('Dhaka')->forceFill(['cake_delivery_enabled' => true])->save();
        $order = $this->placeCakeOrder($cake);
        $this->sendCallback($this->payment($order))->assertOk();
        $this->assertSame(PaymentStatus::PartiallyPaid, $order->fresh()->payment_status);

        $this->staff($this->admin)->get(route('admin.orders.show', $order))->assertOk()->assertSee('Balance due')->assertSee('Record balance received')->assertSee('Thu 26 Nov 2026 · 14:00-17:00');
        $this->staff($this->admin)->post(route('admin.orders.balance', $order), ['method' => 'bkash'])->assertSessionHasErrors('method');
        $this->staff($this->admin)->post(route('admin.orders.balance', $order), ['method' => 'cod'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);

        $second = $this->placeCakeOrder($cake);
        $this->sendCallback($this->payment($second))->assertOk();
        $advance = $second->fresh()->advance_paisa;
        $this->staff($this->admin)->post(route('admin.orders.refund', $second), ['amount' => (string) ($advance / 100), 'reason' => 'Customer cancelled in time'])->assertSessionHasNoErrors();
        $this->assertSame(PaymentStatus::Refunded, $second->fresh()->payment_status, 'Refunding the whole advance is a full refund.');
    }

    public function test_readiness_fails_while_cake_prices_are_test_prices(): void
    {
        $this->cake();
        $this->artisan('app:readiness', ['--target' => 'staging'])->expectsOutputToContain('No TEST custom-cake prices')->assertFailed();
    }

    public function test_storefront_cards_and_product_page_point_cakes_to_the_designer(): void
    {
        $cake = $this->cake();
        $slug = $cake->product->slug;

        $this->get('/product/'.$slug)->assertOk()->assertSee('Design this cake')->assertSee(route('cakes.designer', ['product' => $slug]), false)->assertDontSee('Request a quote for');
    }

    /**
     * Staff who passed MFA "now" on the frozen test clock (the shared helper stamps the real clock).
     */
    private function staff(User $user): static
    {
        return $this->actingAs($user)->withSession(['mfa' => ['verified_at' => now()->timestamp]]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function settingsForm(array $overrides = []): array
    {
        return array_merge([
            'orders_open' => 1, 'daily_capacity' => 6, 'lead_hours_standard' => 48, 'simple_enabled' => 0, 'lead_hours_simple' => 24, 'lead_hours_complex' => 72,
            'time_slots' => "10:00–13:00\n15:00-18:00\n", 'advance_percent' => 30, 'advance_high_value_threshold' => '8,000', 'advance_high_value_percent' => 100,
            'cod_enabled' => 1, 'rush_fee' => '200', 'rush_window_hours' => 60, 'booking_window_days' => 21, 'cancellation_policy' => 'Free cancellation up to 24 hours before.',
        ], $overrides);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function optionForm(array $overrides = []): array
    {
        return array_merge(['group' => 'flavour', 'code' => 'vanilla', 'name' => 'Vanilla', 'price_type' => 'per_kg', 'price' => '1300', 'complexity' => 'simple', 'is_active' => 1], $overrides);
    }

    private function quoteOnlyProduct(): Product
    {
        $product = Product::factory()->create(['name' => 'Celebration cake']);
        $product->variants()->delete();
        $product->variants()->create(['sku' => 'CAKE-'.Str::random(4), 'name' => 'Cake', 'pricing_mode' => PricingMode::QuoteOnly, 'selling_unit' => SellingUnit::Piece, 'stock_quantity' => 0, 'is_active' => true]);

        return $product;
    }

    private function cake(): CakeProduct
    {
        $cake = CakeProduct::query()->create(['product_id' => $this->quoteOnlyProduct()->id, 'min_grams' => 1000, 'max_grams' => 2000, 'step_grams' => 500, 'max_tiers' => 1]);
        CakeOption::query()->create(['group' => 'flavour', 'code' => 'vanilla', 'name' => 'Vanilla', 'price_type' => 'per_kg', 'price_paisa' => 120000, 'is_test' => true, 'complexity' => 'simple', 'is_active' => true]);
        CakeOption::query()->create(['group' => 'shape', 'code' => 'round', 'name' => 'Round', 'price_type' => 'flat', 'price_paisa' => 0, 'is_test' => true, 'complexity' => 'simple', 'is_active' => true]);

        return $cake;
    }

    private function placeCakeOrder(CakeProduct $cake): Order
    {
        $cart = Cart::query()->create(['token' => Str::random(40)]);
        $design = CakeDesign::query()->create([
            'uuid' => (string) Str::uuid(), 'product_variant_id' => $cake->product->variants()->sole()->id, 'status' => CakeDesign::DRAFT,
            'spec' => ['shape' => 'round', 'grams' => 1000, 'tiers' => 1, 'flavour' => 'vanilla', 'filling' => '', 'cream' => '', 'diets' => [], 'message' => '', 'print' => false, 'extras' => [], 'note' => ''],
            'scheduled_for' => '2026-11-26', 'time_slot' => '14:00-17:00',
        ]);
        app(CartService::class)->addCakeDesign($cart, $design);
        $summary = app(CheckoutService::class)->summarize($cart, PricingContext::retail(), 'Dhaka', PaymentMethod::Bkash);
        $details = new CheckoutDetails('Rahim Uddin', '01712345678', null, 'House 1', 'Mirpur', 'Dhaka', null, PaymentMethod::Bkash, Str::random(40), $summary['fingerprint']);

        return app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null)->order;
    }
}

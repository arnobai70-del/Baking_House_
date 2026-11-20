<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PriceListAudience;
use App\Enums\ReservationStatus;
use App\Exceptions\CheckoutChangedException;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\StockReservation;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderPlacer;
use App\Services\Pricing\PricingContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

class CheckoutTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
    }

    public function test_guest_completes_cash_on_delivery_checkout_through_the_web(): void
    {
        $chocolate = $this->weightVariant();
        $cart = $this->cartWith([[$chocolate, 700]]);
        $cookie = [CartService::COOKIE => $cart->token];

        $this->withCookies($cookie)->post('/checkout/review', [
            'name' => 'Rahim', 'phone' => '+880 1712-345678', 'address_line' => 'House 1, Road 2', 'area' => 'Mirpur',
            'district' => 'Dhaka', 'payment_method' => 'cod',
        ])->assertRedirect(route('checkout.review'));

        $review = $this->withCookies($cookie)->get('/checkout/review')->assertOk()->assertSee('৳535', false);
        preg_match('/name="checkout_token" value="([^"]+)"/', $review->getContent(), $token);
        preg_match('/name="fingerprint" value="([^"]+)"/', $review->getContent(), $fingerprint);

        $this->withCookies($cookie)->post('/checkout/place', ['checkout_token' => $token[1], 'fingerprint' => $fingerprint[1]])
            ->assertSessionHasErrors('confirm');

        $response = $this->withCookies($cookie)->post('/checkout/place', ['checkout_token' => $token[1], 'fingerprint' => $fingerprint[1], 'confirm' => 1]);
        $order = Order::query()->sole();
        $response->assertRedirect(route('orders.show', $order));

        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(PaymentStatus::Unpaid, $order->payment_status);
        $this->assertSame('01712345678', $order->customer_phone);
        $this->assertSame([45500, 0, 8000, 53500], [$order->subtotal_paisa, $order->discount_paisa, $order->delivery_paisa, $order->total_paisa]);
        $this->assertSame(9300, $chocolate->fresh()->stock_quantity, 'COD allocates stock at placement.');
        $this->assertSame(0, $cart->fresh()->items()->count(), 'Cart emptied.');
        $this->assertSame('700 g', $order->items()->sole()->quantity_label);
        $this->assertSame([], app(InventoryService::class)->discrepancies());

        $this->withCookies($cookie)->get(route('orders.show', $order))->assertOk()->assertSee($order->number);
    }

    public function test_double_submit_creates_exactly_one_order(): void
    {
        $cart = $this->cartWith([[$this->unitVariant(), 2]]);
        $details = $this->details($cart);

        $first = app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);
        $second = app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);

        $this->assertTrue($first->order->is($second->order));
        $this->assertTrue($second->wasExisting);
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(8, ProductVariant::query()->value('stock_quantity'), 'Stock taken once.');
    }

    public function test_price_change_between_review_and_place_requires_reconfirmation(): void
    {
        $pan = $this->unitVariant(100000);
        $cart = $this->cartWith([[$pan, 1]]);
        $details = $this->details($cart);

        $pan->forceFill(['price_paisa' => 110000])->save();

        $this->expectException(CheckoutChangedException::class);
        app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);
    }

    public function test_expired_promotion_mid_checkout_requires_reconfirmation(): void
    {
        $promotion = Promotion::factory()->percent(1000)->create();
        $cart = $this->cartWith([[$this->unitVariant(100000), 1]]);
        $details = $this->details($cart);

        $promotion->forceFill(['ends_at' => now()->subMinute()])->save();

        try {
            app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);
            $this->fail('Must ask the customer to accept the new total.');
        } catch (CheckoutChangedException $e) {
            $this->assertSame(0, $e->summary['quote']['discount_paisa']);
        }
        $this->assertSame(0, Order::query()->count());
    }

    public function test_stock_sold_out_between_review_and_place_creates_no_order(): void
    {
        $pan = $this->unitVariant(100000, 2);
        $cart = $this->cartWith([[$pan, 2]]);
        $details = $this->details($cart);

        app(InventoryService::class)->reserve($pan, 1, 'someone-else');

        $this->expectException(ValidationException::class);
        try {
            app(OrderPlacer::class)->place($cart, PricingContext::retail(), $details, null);
        } finally {
            $this->assertSame(0, Order::query()->count());
            $this->assertSame(1, $pan->fresh()->reserved_quantity, 'Nothing reserved for the failed order.');
        }
    }

    public function test_promotion_is_recorded_on_the_order_and_redeemed_once(): void
    {
        $promotion = Promotion::factory()->coupon('SAVE10')->percent(1000)->create(['usage_limit' => 5]);
        $cart = $this->cartWith([[$this->unitVariant(100000), 2]]);
        app(CartService::class)->setCoupon($cart, 'save10');

        $order = $this->place($cart)->order;

        $this->assertSame(20000, $order->discount_paisa);
        $this->assertSame('SAVE10', $order->promotion_snapshot[0]['code']);
        $this->assertSame(1, $promotion->fresh()->used_count);
        $this->assertSame(180000 + 8000, $order->total_paisa);
    }

    public function test_free_delivery_threshold_and_default_zone(): void
    {
        $big = $this->place($this->cartWith([[$this->unitVariant(600000, 5, 'BIG'), 1]]))->order;
        $this->assertSame(0, $big->delivery_paisa);

        $outside = app(OrderPlacer::class)->place(
            $cart = $this->cartWith([[$this->unitVariant(10000, 5, 'SMALL'), 1]]),
            PricingContext::retail(),
            $this->details($cart, district: 'Sylhet'),
            null,
        )->order;
        $this->assertSame(15000, $outside->delivery_paisa);
        $this->assertSame('Outside Dhaka', $outside->pricing_snapshot['delivery']['zone']);
    }

    public function test_cash_on_delivery_refused_above_limit(): void
    {
        config(['commerce.cod_max_paisa' => 50000]);
        $cart = $this->cartWith([[$this->unitVariant(100000), 1]]);

        $this->expectException(ValidationException::class);
        $this->place($cart, PaymentMethod::CashOnDelivery);
    }

    public function test_rounding_is_per_line_then_summed(): void
    {
        $odd = $this->weightVariant(65001, 10000, 'ODD');
        $order = $this->place($this->cartWith([[$odd, 150], [$this->unitVariant(1, 5, 'PIN'), 3]]))->order;

        $this->assertSame([9750, 3], $order->items()->orderBy('id')->pluck('line_paisa')->all(), '150 g × ৳650.01/kg = ৳97.5015 → ৳97.50');
        $this->assertSame(9753, $order->subtotal_paisa);
        $this->assertSame(9753 + 8000, $order->total_paisa);
    }

    public function test_order_snapshot_does_not_change_when_prices_change_later(): void
    {
        $pan = $this->unitVariant(100000);
        $order = $this->place($this->cartWith([[$pan, 1]]))->order;

        $pan->forceFill(['price_paisa' => 999900])->save();

        $this->assertSame(100000, $order->fresh()->items()->sole()->line_paisa);
        $this->assertSame(108000, $order->fresh()->total_paisa);
        $this->expectException(\LogicException::class);
        $order->items()->sole()->forceFill(['line_paisa' => 1])->save();
    }

    public function test_business_order_records_organization_and_business_prices(): void
    {
        $pan = $this->unitVariant(100000);
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 80000]])->create();
        $owner = User::factory()->create();
        $organization = Organization::factory()->business()->approved()->withMember($owner)->create();
        $context = PricingContext::forOrganization($organization, $owner->id);

        $cart = app(CartService::class)->forUser($owner);
        app(CartService::class)->addVariant($cart, $pan, 2, $context);
        $order = app(OrderPlacer::class)->place($cart, $context, $this->details($cart, context: $context), $owner)->order;

        $this->assertSame($organization->id, $order->organization_id);
        $this->assertSame('business', $order->audience->value);
        $this->assertSame('business-tier', $order->items()->sole()->rate_source);
        $this->assertSame(160000, $order->subtotal_paisa);
    }

    public function test_orders_are_private_to_their_owner(): void
    {
        $owner = User::factory()->create();
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]], $owner), user: $owner)->order;
        $guestOrder = $this->place($this->cartWith([[$this->unitVariant(5000, 5, 'G1'), 1]]));

        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertNotFound();
        $this->actingAs($owner)->get(route('orders.show', $order))->assertOk();
        $this->get(route('orders.show', $guestOrder->order))->assertNotFound();
        $this->get(route('orders.show', $guestOrder->order).'?key=wrong')->assertNotFound();
        $this->get(route('orders.show', $guestOrder->order).'?key='.$guestOrder->guestKey)->assertOk();
    }

    public function test_order_tracking_needs_the_matching_phone(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]))->order;

        $this->get('/order/track?number='.$order->number.'&phone=01799999999')->assertSee('could not find');
        $this->get('/order/track?number='.$order->number.'&phone=01712345678')->assertSee($order->status->label());
    }

    public function test_online_order_holds_stock_for_15_minutes(): void
    {
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::Bkash)->order;

        $this->assertSame(OrderStatus::PendingPayment, $order->status);
        $this->assertSame(5, $pan->fresh()->stock_quantity);
        $this->assertSame(2, $pan->fresh()->reserved_quantity);
        $this->assertEqualsWithDelta(now()->addMinutes(15)->getTimestamp(), $order->reservation_expires_at->getTimestamp(), 5);
        $this->assertSame(ReservationStatus::Active, StockReservation::query()->sole()->status);
    }
}

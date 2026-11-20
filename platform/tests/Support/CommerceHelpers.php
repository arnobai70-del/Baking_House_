<?php

namespace Tests\Support;

use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\DeliveryZone;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Checkout\CheckoutDetails;
use App\Services\Checkout\CheckoutService;
use App\Services\Orders\OrderPlacer;
use App\Services\Orders\PlacedOrder;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Pricing\PricingContext;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

/**
 * Shared setup for Phase 5 tests (all data illustrative).
 */
trait CommerceHelpers
{
    protected function zones(): void
    {
        DeliveryZone::query()->create(['name' => 'Inside Dhaka', 'districts' => ['Dhaka'], 'charge_paisa' => 8000, 'free_over_paisa' => 500000, 'cod_available' => true, 'is_active' => true]);
        DeliveryZone::query()->create(['name' => 'Outside Dhaka', 'districts' => [], 'is_default' => true, 'charge_paisa' => 15000, 'cod_available' => true, 'is_active' => true]);
    }

    protected function weightVariant(int $perKgPaisa = 65000, int $stockGrams = 10000, string $sku = 'CHOC'): ProductVariant
    {
        return Product::factory()->withWeightVariant($perKgPaisa, $stockGrams, ['sku' => $sku])->create()->variants()->sole();
    }

    protected function unitVariant(int $pricePaisa = 100000, int $stock = 10, string $sku = 'PAN'): ProductVariant
    {
        return Product::factory()->withFixedVariant($pricePaisa, $stock, ['sku' => $sku])->create()->variants()->sole();
    }

    /**
     * @param  list<array{0: ProductVariant, 1: int}>  $lines
     */
    protected function cartWith(array $lines, ?User $user = null): Cart
    {
        $carts = app(CartService::class);
        $cart = $user ? $carts->forUser($user) : Cart::query()->create(['token' => Str::random(40)]);
        foreach ($lines as [$variant, $quantity]) {
            $carts->addVariant($cart, $variant, $quantity, PricingContext::retail($user?->id));
        }

        return $cart;
    }

    protected function details(Cart $cart, PaymentMethod $method = PaymentMethod::CashOnDelivery, string $district = 'Dhaka', ?PricingContext $context = null, ?string $token = null): CheckoutDetails
    {
        $summary = app(CheckoutService::class)->summarize($cart, $context ?? PricingContext::retail(), $district, $method);

        return new CheckoutDetails('Rahim Uddin', '01712345678', 'rahim@example.com', 'House 1, Road 2', 'Mirpur', $district, null, $method, $token ?? Str::random(40), $summary['fingerprint']);
    }

    protected function place(Cart $cart, PaymentMethod $method = PaymentMethod::CashOnDelivery, ?User $user = null, ?PricingContext $context = null): PlacedOrder
    {
        $context ??= PricingContext::retail($user?->id);

        return app(OrderPlacer::class)->place($cart, $context, $this->details($cart, $method, context: $context), $user);
    }

    /**
     * Send a signed TEST-mode provider callback through the real webhook endpoint.
     *
     * @param  array<string, mixed>  $overrides
     */
    protected function sendCallback(Payment $payment, string $type = 'payment.succeeded', array $overrides = [], ?int $timestamp = null, ?string $secret = null): TestResponse
    {
        $gateway = app(PaymentGatewayFactory::class)->for($payment->provider);
        $payload = array_merge([
            'event_id' => 'evt_'.Str::random(16), 'type' => $type, 'reference' => $payment->reference,
            'transaction_id' => 'TX'.Str::random(8), 'amount_paisa' => $payment->amount_paisa, 'currency' => 'BDT',
        ], $overrides);
        $signed = $gateway->signedCallback($payload, $timestamp);
        if ($secret !== null) {
            $signed['headers']['X-BH-Test-Signature'] = hash_hmac('sha256', $signed['headers']['X-BH-Test-Timestamp'].'.'.$signed['body'], $secret);
        }

        return $this->call('POST', '/api/v1/webhooks/payments/'.$payment->provider->value, [], [], [], $this->transformHeadersToServerVars($signed['headers'] + ['Content-Type' => 'application/json']), $signed['body']);
    }

    protected function payment(Order $order): Payment
    {
        return $order->payments()->reorder()->latest('id')->firstOrFail();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Order;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Cart, checkout and order API (Sanctum, orders:read / orders:write).
 */
class OrderApiTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    private function api(User $user, array $abilities = ['orders:read', 'orders:write', 'account:read', 'business:read']): static
    {
        $this->app['auth']->forgetGuards();
        $this->flushHeaders();

        return $this->withHeader('Authorization', 'Bearer '.$user->createToken('t', $abilities)->plainTextToken);
    }

    private array $address = ['name' => 'Rahim', 'phone' => '01712345678', 'address_line' => 'House 1, Road 2', 'area' => 'Mirpur', 'district' => 'Dhaka', 'payment_method' => 'cod'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
    }

    public function test_full_api_checkout_with_idempotency_key(): void
    {
        $this->unitVariant(100000, 5, 'PAN');
        $user = User::factory()->create();

        $this->api($user)->postJson('/api/v1/cart/items', ['sku' => 'pan', 'quantity' => 2])->assertCreated()->assertJsonPath('data.subtotal_paisa', 200000);
        $preview = $this->api($user)->postJson('/api/v1/checkout/preview', ['district' => 'Dhaka', 'payment_method' => 'cod'])->assertOk();
        $this->assertSame(208000, $preview->json('data.total_paisa'));

        $payload = $this->address + ['fingerprint' => $preview->json('data.fingerprint')];
        $this->api($user)->postJson('/api/v1/orders', $payload)->assertUnprocessable()->assertJsonValidationErrors('idempotency_key');

        $first = $this->api($user)->withHeader('Idempotency-Key', 'key-1234567890abcd')->postJson('/api/v1/orders', $payload)->assertCreated();
        $replay = $this->api($user)->withHeader('Idempotency-Key', 'key-1234567890abcd')->postJson('/api/v1/orders', $payload)->assertOk();

        $this->assertSame($first->json('data.number'), $replay->json('data.number'));
        $this->assertTrue($replay->json('replayed'));
        $this->assertSame(1, Order::query()->count());

        $this->api($user)->getJson('/api/v1/orders')->assertJsonCount(1, 'data');
        $this->api($user)->getJson('/api/v1/orders/'.$first->json('data.number'))->assertOk()->assertJsonPath('data.total_paisa', 208000);
    }

    public function test_changed_total_returns_409_with_new_summary(): void
    {
        $pan = $this->unitVariant(100000, 5, 'PAN');
        $user = User::factory()->create();
        $this->api($user)->postJson('/api/v1/cart/items', ['sku' => 'PAN', 'quantity' => 1]);
        $fingerprint = $this->api($user)->postJson('/api/v1/checkout/preview', ['district' => 'Dhaka', 'payment_method' => 'cod'])->json('data.fingerprint');

        $pan->forceFill(['price_paisa' => 150000])->save();

        $this->api($user)->withHeader('Idempotency-Key', 'key-changed-0000001')->postJson('/api/v1/orders', $this->address + ['fingerprint' => $fingerprint])
            ->assertStatus(409)->assertJsonPath('summary.subtotal_paisa', 150000);
        $this->assertSame(0, Order::query()->count());
    }

    public function test_other_customers_orders_are_invisible(): void
    {
        $owner = User::factory()->create();
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]], $owner), user: $owner)->order;

        $this->api(User::factory()->create())->getJson('/api/v1/orders/'.$order->number)->assertNotFound();
        $this->api(User::factory()->create())->getJson('/api/v1/orders')->assertJsonCount(0, 'data');
    }

    public function test_abilities_are_enforced(): void
    {
        $user = User::factory()->create();

        $this->api($user, ['orders:read'])->postJson('/api/v1/cart/items', ['sku' => 'X', 'quantity' => 1])->assertForbidden();
        $this->api($user, ['account:read'])->getJson('/api/v1/orders')->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->flushHeaders()->getJson('/api/v1/cart')->assertUnauthorized();
    }

    public function test_business_api_checkout_uses_business_prices_and_rejects_foreign_organization(): void
    {
        $pan = $this->unitVariant(100000, 10, 'PAN');
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 80000]])->create();
        $owner = User::factory()->create();
        $organization = Organization::factory()->business()->approved()->withMember($owner)->create();
        $foreign = Organization::factory()->business()->approved()->create();

        $this->api($owner)->postJson('/api/v1/cart/items', ['sku' => 'PAN', 'quantity' => 1])->assertJsonPath('data.subtotal_paisa', 80000);
        $this->api($owner)->withHeader('X-Organization-Id', (string) $foreign->id)->getJson('/api/v1/cart')->assertForbidden();

        $fingerprint = $this->api($owner)->postJson('/api/v1/checkout/preview', ['district' => 'Dhaka', 'payment_method' => 'cod'])->json('data.fingerprint');
        $this->api($owner)->withHeader('Idempotency-Key', 'biz-order-00000001')->postJson('/api/v1/orders', $this->address + ['fingerprint' => $fingerprint])->assertCreated();

        $this->assertSame($organization->id, Order::query()->sole()->organization_id);
    }

    public function test_online_api_order_returns_a_test_payment_url(): void
    {
        $this->unitVariant(100000, 5, 'PAN');
        $user = User::factory()->create();
        $this->api($user)->postJson('/api/v1/cart/items', ['sku' => 'PAN', 'quantity' => 1]);
        $fingerprint = $this->api($user)->postJson('/api/v1/checkout/preview', ['district' => 'Dhaka', 'payment_method' => 'bkash'])->json('data.fingerprint');

        $response = $this->api($user)->withHeader('Idempotency-Key', 'online-0000000001')
            ->postJson('/api/v1/orders', ['payment_method' => 'bkash', 'fingerprint' => $fingerprint] + $this->address)->assertCreated();

        $this->assertStringContainsString('/payments/test/', (string) $response->json('payment_url'));
        $this->assertSame('pending_payment', $response->json('data.status'));
    }
}

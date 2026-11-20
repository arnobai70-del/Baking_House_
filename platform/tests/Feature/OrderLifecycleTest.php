<?php

namespace Tests\Feature;

use App\Enums\InventoryMovementType;
use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\AuditLog;
use App\Models\DeliveryZone;
use App\Models\InventoryMovement;
use App\Models\NotificationLog;
use App\Models\Permission;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Inventory\InventoryService;
use App\Services\Notifications\SmsGateway;
use App\Services\Orders\OrderService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Status transitions, cancellation + stock restoration, expiry, notifications and staff access.
 */
class OrderLifecycleTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_staff_moves_an_order_through_fulfillment_in_order_only(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]))->order;

        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'shipped'])->assertSessionHasErrors('status');
        foreach (['processing', 'packed', 'shipped', 'delivered'] as $status) {
            $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'processing'])->assertSessionHasErrors('status');

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
        $this->assertSame(['confirmed', 'processing', 'packed', 'shipped', 'delivered'], $order->events()->where('kind', 'fulfillment')->pluck('to_status')->all());
        $this->assertSame(4, AuditLog::query()->where('action', 'order.status_changed')->count());
    }

    public function test_customer_cancels_unpaid_online_order_and_reservation_is_released(): void
    {
        $owner = User::factory()->create();
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 3]], $owner), PaymentMethod::Bkash, $owner)->order;

        $this->actingAs($owner)->post(route('orders.cancel', $order), ['reason' => 'Ordered by mistake'])->assertRedirect();

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame([5, 0], [$pan->fresh()->stock_quantity, $pan->fresh()->reserved_quantity]);
        $this->assertSame('cancelled', $order->payments()->sole()->status->value);
    }

    public function test_staff_cancel_of_allocated_order_restocks_through_the_ledger(): void
    {
        $chocolate = $this->weightVariant(65000, 10000);
        $order = $this->place($this->cartWith([[$chocolate, 2500]]))->order;
        $this->assertSame(7500, $chocolate->fresh()->stock_quantity);

        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'processing']);
        $this->actingAsStaff($this->admin)->post(route('admin.orders.cancel', $order), ['reason' => 'Customer called to cancel'])->assertSessionHasNoErrors();

        $this->assertSame(10000, $chocolate->fresh()->stock_quantity);
        $restock = InventoryMovement::query()->where('type', InventoryMovementType::Restock->value)->sole();
        $this->assertSame(2500, $restock->quantity);
        $this->assertSame([], app(InventoryService::class)->discrepancies());
    }

    public function test_cancelling_twice_never_restocks_twice(): void
    {
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 2]]))->order;
        $orders = app(OrderService::class);

        $orders->cancel($order, 'First', $this->admin, byCustomer: false);
        try {
            $orders->cancel($order->fresh(), 'Second', $this->admin, byCustomer: false);
        } catch (ValidationException) {
        }

        $this->assertSame(5, $pan->fresh()->stock_quantity);
        $this->assertSame(1, InventoryMovement::query()->where('type', 'restock')->count());
    }

    public function test_customer_cannot_cancel_once_processing_and_nobody_after_shipping(): void
    {
        $owner = User::factory()->create();
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]], $owner), user: $owner)->order;
        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'processing']);

        $this->actingAs($owner)->post(route('orders.cancel', $order), ['reason' => 'Too late?'])->assertSessionHasErrors('status');

        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'packed']);
        $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => 'shipped']);
        $this->actingAsStaff($this->admin)->post(route('admin.orders.cancel', $order), ['reason' => 'Try'])->assertSessionHasErrors('status');
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status);
    }

    public function test_cancelled_order_gives_promotion_usage_back(): void
    {
        $promotion = Promotion::factory()->coupon('ONE')->create(['usage_limit' => 1]);
        $cart = $this->cartWith([[$this->unitVariant(), 1]]);
        app(CartService::class)->setCoupon($cart, 'ONE');
        $order = $this->place($cart)->order;
        $this->assertSame(1, $promotion->fresh()->used_count);

        app(OrderService::class)->cancel($order, 'Cancelled', $this->admin, byCustomer: false);

        $this->assertSame(0, $promotion->fresh()->used_count);
    }

    public function test_unpaid_orders_expire_and_restore_stock(): void
    {
        $pan = $this->unitVariant(100000, 10);
        $online = $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::Nagad)->order;
        $bank = $this->place($this->cartWith([[$pan, 3]]), PaymentMethod::BankTransfer)->order;
        $this->assertSame(7, $pan->fresh()->stock_quantity);

        $this->travel(30)->minutes();
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('Cancelled 0');

        $this->travel(2)->hours();
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('Cancelled 1');
        $this->assertSame(OrderStatus::Cancelled, $online->fresh()->status);

        $this->travel(2)->days();
        $this->artisan('orders:expire-unpaid')->expectsOutputToContain('Cancelled 1');
        $this->assertSame(OrderStatus::Cancelled, $bank->fresh()->status);
        $this->assertSame([10, 0], [$pan->fresh()->stock_quantity, $pan->fresh()->reserved_quantity]);
        $this->assertSame([], app(InventoryService::class)->discrepancies());
    }

    public function test_customer_notifications_are_logged_with_masked_recipients(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]))->order;

        $sms = NotificationLog::query()->where('channel', 'sms')->where('template', 'order_placed')->sole();
        $this->assertSame('log', $sms->driver);
        $this->assertSame('017******78', $sms->recipient_masked);
        $this->assertSame('r****@example.com', NotificationLog::query()->where('channel', 'email')->sole()->recipient_masked);

        foreach (['processing', 'packed', 'shipped'] as $status) {
            $this->actingAsStaff($this->admin)->post(route('admin.orders.transition', $order), ['status' => $status]);
        }
        $this->assertSame(1, NotificationLog::query()->where('template', 'status_shipped')->where('channel', 'sms')->count());
    }

    public function test_a_failing_notification_never_breaks_the_order(): void
    {
        config(['commerce.notifications.sms_driver' => 'missing-provider']);
        $this->app->forgetInstance(SmsGateway::class);

        $this->expectException(\RuntimeException::class);
        app(SmsGateway::class);
    }

    public function test_staff_order_screens_need_order_permission(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]))->order;
        $role = Role::create(['name' => 'catalog_only', 'label' => 'Catalog only']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'catalog.manage'])->pluck('id'));
        $editor = User::factory()->withMfa()->withRole('catalog_only')->create();

        $this->actingAsStaff($editor)->get('/admin/orders')->assertForbidden();
        $this->actingAsStaff($editor)->post(route('admin.orders.cancel', $order), ['reason' => 'no'])->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.orders.show', $order))->assertForbidden();

        foreach (['/admin/orders', '/admin/orders?status=confirmed', route('admin.orders.show', $order), '/admin/orders/reconciliation', '/admin/zones', '/admin/zones/create'] as $url) {
            $this->actingAsStaff($this->admin)->get($url)->assertOk();
        }
    }

    public function test_delivery_zone_admin_rejects_overlapping_districts(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/zones', ['name' => 'Overlap', 'districts' => ['Dhaka'], 'charge' => '60', 'is_active' => 1])
            ->assertSessionHasErrors('districts');
        $this->actingAsStaff($this->admin)->post('/admin/zones', ['name' => 'Chattogram', 'districts' => ['Chattogram'], 'charge' => '120', 'is_active' => 1, 'cod_available' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(12000, DeliveryZone::forDistrict('Chattogram')->charge_paisa);
    }
}

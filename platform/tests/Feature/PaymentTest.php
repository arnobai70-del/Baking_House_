<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Inventory\InventoryService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentGatewayFactory;
use App\Services\Payments\PaymentService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Support\CommerceHelpers;
use Tests\TestCase;

/**
 * Payment callbacks: verification, idempotency, no double capture, late payments, refunds.
 */
class PaymentTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
    }

    public function test_verified_success_callback_confirms_and_commits_stock(): void
    {
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::Bkash)->order;

        $this->sendCallback($this->payment($order))->assertOk()->assertJsonPath('result', 'paid_confirmed');

        $order->refresh();
        $this->assertSame(OrderStatus::Confirmed, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame([3, 0], [$pan->fresh()->stock_quantity, $pan->fresh()->reserved_quantity]);
        $this->assertSame(PaymentRecordStatus::Paid, $this->payment($order)->status);
        $this->assertSame([], app(InventoryService::class)->discrepancies());
    }

    public function test_duplicate_and_replayed_callbacks_never_capture_twice(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Nagad)->order;
        $payment = $this->payment($order);

        $this->sendCallback($payment, overrides: ['event_id' => 'evt_same'])->assertJsonPath('result', 'paid_confirmed');
        $this->sendCallback($payment, overrides: ['event_id' => 'evt_same'])->assertOk()->assertJsonPath('result', 'duplicate');
        $this->sendCallback($payment, overrides: ['event_id' => 'evt_other'])->assertJsonPath('result', 'already_processed');

        $this->assertSame(1, AuditLog::query()->where('action', 'payment.captured')->count());
        $this->assertSame(2, PaymentEvent::query()->count());
    }

    public function test_bad_signature_and_stale_callbacks_are_rejected(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Card)->order;
        $payment = $this->payment($order);

        $this->sendCallback($payment, secret: 'attacker-secret')->assertStatus(401);
        $this->sendCallback($payment, timestamp: now()->subMinutes(10)->getTimestamp())->assertStatus(401);
        $this->postJson('/api/v1/webhooks/payments/card', ['event_id' => 'x', 'type' => 'payment.succeeded', 'reference' => $payment->reference])->assertStatus(401);

        $this->assertSame(PaymentStatus::Pending, $order->fresh()->payment_status);
        $this->assertSame(3, PaymentEvent::query()->where('signature_valid', false)->count());
        $this->postJson('/api/v1/webhooks/payments/cod', [])->assertNotFound();
    }

    public function test_amount_or_currency_mismatch_is_not_captured(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Bkash)->order;

        $this->sendCallback($this->payment($order), overrides: ['amount_paisa' => 1])->assertJsonPath('result', 'amount_mismatch');
        $this->sendCallback($this->payment($order), overrides: ['currency' => 'USD'])->assertJsonPath('result', 'amount_mismatch');

        $this->assertFalse($order->fresh()->isPaid());
        $this->assertSame(PaymentRecordStatus::Initiated, $this->payment($order)->status);
    }

    public function test_failed_payment_keeps_order_open_for_a_retry(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Bkash)->order;

        $this->sendCallback($this->payment($order), 'payment.failed')->assertJsonPath('result', 'payment_failed');
        $this->assertSame([OrderStatus::PendingPayment, PaymentStatus::Failed], [$order->fresh()->status, $order->fresh()->payment_status]);

        app(PaymentService::class)->checkoutUrl($order->fresh());
        $retry = $this->payment($order);
        $this->assertSame(PaymentRecordStatus::Initiated, $retry->status);
        $this->assertSame(2, $order->payments()->count());

        $this->sendCallback($retry)->assertJsonPath('result', 'paid_confirmed');
    }

    public function test_late_payment_reallocates_stock_when_still_available(): void
    {
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::Bkash)->order;

        $this->travel(20)->minutes();
        $this->artisan('inventory:release-expired');
        $this->assertSame(0, $pan->fresh()->reserved_quantity);

        $this->sendCallback($this->payment($order))->assertJsonPath('result', 'paid_confirmed');
        $this->assertSame(OrderStatus::Confirmed, $order->fresh()->status);
        $this->assertSame(3, $pan->fresh()->stock_quantity);
    }

    public function test_late_payment_never_confirms_against_unavailable_stock(): void
    {
        $pan = $this->unitVariant(100000, 2);
        $order = $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::Bkash)->order;

        $this->travel(20)->minutes();
        $this->artisan('inventory:release-expired');
        $this->place($this->cartWith([[$pan, 2]]), PaymentMethod::CashOnDelivery);

        $this->sendCallback($this->payment($order))->assertJsonPath('result', 'paid_stock_unavailable_review');

        $order->refresh();
        $this->assertSame(OrderStatus::PaymentReview, $order->status);
        $this->assertSame(PaymentStatus::Paid, $order->payment_status);
        $this->assertSame(0, $pan->fresh()->stock_quantity);
        $this->assertGreaterThanOrEqual(0, $pan->fresh()->stock_quantity, 'Never oversold.');
        $this->assertContains('Order in payment review (paid after stock hold expired).', array_column(app(PaymentService::class)->reconciliationIssues(), 'issue'));
    }

    public function test_payment_after_cancellation_is_flagged_for_refund(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Bkash)->order;
        $payment = $this->payment($order);
        app(OrderService::class)->cancel($order, 'Customer changed mind', null, byCustomer: true);

        Payment::query()->whereKey($payment->id)->update(['status' => 'initiated']);
        $this->sendCallback($payment->fresh())->assertJsonPath('result', 'paid_after_cancel_refund_due');

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertContains('Paid order is cancelled — refund due.', array_column(app(PaymentService::class)->reconciliationIssues(), 'issue'));
    }

    public function test_refunds_partial_then_full_and_never_more_than_paid(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $order = $this->place($this->cartWith([[$this->unitVariant(100000), 1]]), PaymentMethod::Card)->order;
        $this->sendCallback($this->payment($order));
        $payments = app(PaymentService::class);

        $payments->refund($order->fresh(), 30000, 'Damaged item', $admin);
        $this->assertSame(PaymentStatus::PartiallyRefunded, $order->fresh()->payment_status);

        try {
            $payments->refund($order->fresh(), 999999, 'Too much', $admin);
            $this->fail('Over-refund must fail.');
        } catch (ValidationException) {
        }

        $payments->refund($order->fresh(), 78000, 'Full refund', $admin);
        $this->assertSame(PaymentStatus::Refunded, $order->fresh()->payment_status);
        $this->assertSame(108000, $order->fresh()->refunded_paisa);
        $this->assertSame(2, $order->refunds()->count());
        $this->assertSame([], app(PaymentService::class)->reconciliationIssues());
    }

    public function test_bank_transfer_is_confirmed_by_staff_with_exact_amount(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $pan = $this->unitVariant(100000, 5);
        $order = $this->place($this->cartWith([[$pan, 1]]), PaymentMethod::BankTransfer)->order;

        $this->assertSame(4, $pan->fresh()->stock_quantity, 'Bank transfer allocates stock at placement.');

        $this->actingAsStaff($admin)->post(route('admin.orders.bank-transfer', $order), ['amount' => '100', 'bank_reference' => 'TRX1'])->assertSessionHasErrors('amount');
        $this->actingAsStaff($admin)->post(route('admin.orders.bank-transfer', $order), ['amount' => '1080', 'bank_reference' => 'TRX1'])->assertSessionHasNoErrors();

        $this->assertSame([OrderStatus::Confirmed, PaymentStatus::Paid], [$order->fresh()->status, $order->fresh()->payment_status]);
    }

    public function test_cash_on_delivery_is_collected_after_shipping(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]))->order;

        $this->actingAsStaff($admin)->post(route('admin.orders.cash-collected', $order))->assertSessionHasErrors('payment');

        foreach (['processing', 'packed', 'shipped'] as $status) {
            $this->actingAsStaff($admin)->post(route('admin.orders.transition', $order), ['status' => $status])->assertSessionHasNoErrors();
        }
        $this->actingAsStaff($admin)->post(route('admin.orders.cash-collected', $order))->assertSessionHasNoErrors();

        $this->assertSame(PaymentStatus::Paid, $order->fresh()->payment_status);
        $this->assertSame(OrderStatus::Shipped, $order->fresh()->status, 'Payment status is separate from fulfillment.');
    }

    public function test_test_payment_page_requires_a_signed_url_and_completes_via_callback(): void
    {
        $order = $this->place($this->cartWith([[$this->unitVariant(), 1]]), PaymentMethod::Bkash)->order;
        $payment = $this->payment($order);

        $this->get('/payments/test/'.$payment->reference)->assertForbidden();
        $this->get(app(PaymentGatewayFactory::class)->for(PaymentMethod::Bkash)->checkoutUrl($payment))->assertOk()->assertSee('TEST MODE');

        $this->post(URL::temporarySignedRoute('payments.test.complete', now()->addMinutes(5), ['reference' => $payment->reference]), ['outcome' => 'success'])
            ->assertRedirect(route('orders.show', $order));
        $this->assertTrue($order->fresh()->isPaid());
        $this->assertSame(1, PaymentEvent::query()->where('signature_valid', true)->count(), 'Simulator went through the verified callback path.');
    }

    public function test_live_mode_is_refused_until_real_gateways_exist(): void
    {
        config(['commerce.payments.mode' => 'live']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Live payment gateways are not configured');
        app(PaymentGatewayFactory::class)->for(PaymentMethod::Bkash);
    }

    public function test_no_card_or_wallet_credentials_are_stored(): void
    {
        $this->assertSame([], array_values(array_filter(
            array_merge(Schema::getColumnListing('payments'), Schema::getColumnListing('payment_events'), Schema::getColumnListing('orders')),
            fn (string $column) => preg_match('/^(card_number|card_pan|pan|cvv|cvc|card_expiry|expiry|pin|wallet_pin|wallet_password|otp)$/', $column) === 1,
        )));
    }
}

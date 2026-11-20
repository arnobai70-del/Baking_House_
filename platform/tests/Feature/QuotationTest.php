<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Enums\QuotationStatus;
use App\Models\AuditLog;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Order;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;
use App\Services\Pricing\PricingContext;
use App\Services\Quotations\QuotationService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * RFQ → quote → accept → order, at exactly the quoted lines and total, once.
 */
class QuotationTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    public function test_public_request_becomes_a_lead_and_a_member_request_belongs_to_the_organization(): void
    {
        $this->post('/quotations', ['contact_name' => 'Guest Chef', 'contact_phone' => '+880 1711-111111', 'request_text' => 'Deck oven × 1'])
            ->assertSessionHasNoErrors()->assertSessionHas('status');
        $lead = Quotation::query()->sole();
        $this->assertNull($lead->organization_id);
        $this->assertSame('01711111111', $lead->contact_phone);
        $this->assertSame(QuotationStatus::Requested, $lead->status);

        [$organization, $owner] = $this->businessWith();
        $this->actingAs($owner)->get('/business/dashboard');
        $this->actingAs($owner)->post('/quotations', ['contact_name' => 'Owner', 'contact_phone' => '01722222222', 'request_text' => 'Soy sauce cartons × 20'])
            ->assertRedirect();
        $this->assertSame($organization->id, Quotation::query()->latest('id')->first()->organization_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.requested']);
    }

    public function test_request_form_validates_phone_and_drops_bots(): void
    {
        $this->post('/quotations', ['contact_name' => 'X', 'contact_phone' => '12345', 'request_text' => 'Oven'])->assertSessionHasErrors('contact_phone');
        $this->post('/quotations', ['contact_name' => 'Bot', 'contact_phone' => '01711111111', 'request_text' => 'Spam', 'website' => 'http://spam.example'])->assertSessionHas('status');
        $this->assertSame(0, Quotation::query()->count());
    }

    public function test_staff_issues_a_quote_with_fixed_lines_and_a_custom_line(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->weightVariant(65000, 50000, 'CHOC');
        $this->unitVariant(100000, 5, 'OVEN');
        $quotation = app(QuotationService::class)->request(['contact_name' => 'O', 'contact_phone' => '01711111111', 'contact_email' => null, 'request_text' => 'Need stuff', 'customer_note' => null], $owner, $organization);

        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $quotation), [
            'lines' => [
                ['sku' => 'choc', 'description' => '', 'quantity' => '25kg', 'price' => '14,500'],
                ['sku' => 'OVEN', 'description' => 'Deck oven', 'quantity' => '1', 'price' => '95000'],
                ['sku' => '', 'description' => 'Installation', 'quantity' => '1', 'price' => '2500'],
                ['sku' => '', 'description' => '', 'quantity' => '', 'price' => ''],
            ],
            'valid_until' => now('Asia/Dhaka')->addDays(10)->format('Y-m-d'),
            'terms' => 'Delivery in 5 working days',
        ])->assertSessionHasNoErrors();

        $quotation->refresh();
        $this->assertSame(QuotationStatus::Quoted, $quotation->status);
        $this->assertSame(1450000 + 9500000 + 250000, $quotation->total_paisa);
        $this->assertSame([25000, 1, 1], $quotation->items()->pluck('quantity')->all());
        $this->assertSame('25 kg', $quotation->items()->first()->quantity_label);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.issued']);

        $this->actingAs($owner)->get(route('business.quotations.show', $quotation))->assertOk()
            ->assertSee('৳1,12,000', false)->assertSee('Installation')->assertSee('Delivery in 5 working days')->assertDontSee('Internal');
    }

    public function test_issue_rejects_unknown_skus_bad_quantities_and_leads(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->weightVariant(65000, 50000, 'CHOC');
        $quotation = app(QuotationService::class)->request(['contact_name' => 'O', 'contact_phone' => '01711111111', 'contact_email' => null, 'request_text' => 'x', 'customer_note' => null], $owner, $organization);
        $date = now('Asia/Dhaka')->addDays(3)->format('Y-m-d');

        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $quotation), ['lines' => [['sku' => 'NOPE', 'quantity' => '1', 'price' => '10']], 'valid_until' => $date])->assertSessionHasErrors('lines.0.sku');
        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $quotation), ['lines' => [['sku' => 'CHOC', 'quantity' => '2.5 lbs', 'price' => '10']], 'valid_until' => $date])->assertSessionHasErrors('lines.0.quantity');
        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $quotation), ['lines' => [['sku' => 'CHOC', 'quantity' => '1kg', 'price' => '0']], 'valid_until' => $date])->assertSessionHasErrors('lines.0.price');
        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $quotation), ['lines' => [['sku' => 'CHOC', 'quantity' => '1kg', 'price' => '10']], 'valid_until' => now('Asia/Dhaka')->subDay()->format('Y-m-d')])->assertSessionHasErrors('valid_until');
        $this->assertSame(QuotationStatus::Requested, $quotation->fresh()->status);

        $lead = app(QuotationService::class)->request(['contact_name' => 'G', 'contact_phone' => '01711111111', 'contact_email' => null, 'request_text' => 'x', 'customer_note' => null], null, null);
        $this->actingAsStaff($this->admin)->post(route('admin.quotations.issue', $lead), ['lines' => [['sku' => 'CHOC', 'quantity' => '1kg', 'price' => '10']], 'valid_until' => $date])->assertSessionHasErrors('status');
        $this->actingAsStaff($this->admin)->post(route('admin.quotations.close', $lead), ['reason' => 'Called, bought elsewhere'])->assertSessionHasNoErrors();
        $this->assertSame(QuotationStatus::Closed, $lead->fresh()->status);
    }

    public function test_accepted_quote_is_ordered_once_at_exactly_the_quoted_price(): void
    {
        [$organization, $owner] = $this->businessWith();
        $choc = $this->weightVariant(65000, 50000, 'CHOC');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'CHOC', 'quantity' => '20kg', 'price' => '11000'], ['description' => 'Delivery to kitchen', 'quantity' => '1', 'price' => '500']]);
        Promotion::factory()->percent(1000)->create();

        $this->actingAs($owner)->post(route('business.quotations.accept', $quotation))->assertRedirect(route('cart.show'));
        $this->assertSame(QuotationStatus::Accepted, $quotation->fresh()->status);

        $context = PricingContext::forOrganization($organization, $owner->id);
        $cart = Cart::query()->where('user_id', $owner->id)->sole();
        $quote = app(CartService::class)->quote($cart, $context);
        $this->assertSame(1150000, $quote['subtotal_paisa']);
        $this->assertSame(0, $quote['discount_paisa'], 'Coupons / promotions never stack on quoted prices.');

        $order = $this->place($cart, PaymentMethod::CashOnDelivery, $owner, $context)->order;

        $this->assertSame(1150000, $order->subtotal_paisa);
        $line = $order->items()->sole();
        $this->assertSame('quotation', $line->type);
        $this->assertSame($quotation->number, $line->sku);
        $this->assertCount(2, $line->components);
        $this->assertSame(30000, $choc->fresh()->stock_quantity, '20 kg allocated from stock (COD).');
        $this->assertSame(QuotationStatus::Ordered, $quotation->fresh()->status);
        $this->assertSame($order->id, $quotation->fresh()->order_id);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quotation.ordered']);

        $this->actingAs($owner)->post(route('business.quotations.accept', $quotation))->assertSessionHasErrors('quotation');
        $this->assertSame(1, Order::query()->count());
    }

    public function test_a_quotation_line_cannot_be_ordered_twice_even_from_two_carts(): void
    {
        [$organization, $owner] = $this->businessWith();
        $buyer = $this->addMember($organization, OrganizationRole::Buyer);
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '2', 'price' => '150000']]);
        app(QuotationService::class)->accept($quotation, $owner, $organization);

        $carts = app(CartService::class);
        $ownerContext = PricingContext::forOrganization($organization, $owner->id);
        $buyerContext = PricingContext::forOrganization($organization, $buyer->id);
        $carts->addQuotation($ownerCart = $carts->forUser($owner), $quotation->fresh(), $ownerContext);
        $carts->addQuotation($buyerCart = $carts->forUser($buyer), $quotation->fresh(), $buyerContext);

        $this->place($ownerCart, PaymentMethod::BankTransfer, $owner, $ownerContext);
        $this->expectException(ValidationException::class);
        try {
            $this->place($buyerCart, PaymentMethod::BankTransfer, $buyer, $buyerContext);
        } finally {
            $this->assertSame(1, Order::query()->count());
            $this->assertSame(8, ProductVariant::query()->where('sku', 'OVEN')->value('stock_quantity'), 'Stock taken once.');
        }
    }

    public function test_quoted_quantities_cannot_be_changed_in_the_cart(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '2', 'price' => '150000']]);
        $this->actingAs($owner)->post(route('business.quotations.accept', $quotation));
        $item = Cart::query()->where('user_id', $owner->id)->sole()->items()->sole();

        $this->actingAs($owner)->patch(route('cart.items.update', $item), ['unit_quantity' => 5])->assertSessionHasErrors('quantity');
        $this->assertSame(1, $item->fresh()->quantity);
        $this->actingAs($owner)->delete(route('cart.items.destroy', $item))->assertRedirect();
        $this->assertNull($item->fresh());
    }

    public function test_expired_or_foreign_quotes_are_never_priced(): void
    {
        [$organization, $owner] = $this->businessWith();
        [$other, $otherOwner] = $this->businessWith(name: 'Other Restaurant');
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '1', 'price' => '50000']], validDays: 2);
        app(QuotationService::class)->accept($quotation, $owner, $organization);

        $this->actingAs($otherOwner)->post(route('business.quotations.accept', $quotation))->assertNotFound();
        $this->expectsForeignAdd($quotation, $other, $otherOwner);

        $carts = app(CartService::class);
        $context = PricingContext::forOrganization($organization, $owner->id);
        $carts->addQuotation($cart = $carts->forUser($owner), $quotation->fresh(), $context);
        $this->assertTrue($carts->quote($cart, $context)['valid']);
        $this->assertFalse($carts->quote($cart, PricingContext::retail($owner->id))['valid'], 'Retail context cannot use a business quote.');

        $this->travel(3)->days();
        $quote = $carts->quote($cart, PricingContext::forOrganization($organization, $owner->id));
        $this->assertFalse($quote['valid']);
        $this->assertStringContainsString('expired', $quote['lines'][0]['error']);

        $this->artisan('quotations:expire')->expectsOutputToContain('Expired 1');
        $this->assertSame(QuotationStatus::Expired, $quotation->fresh()->status);
    }

    public function test_cancelling_the_order_reopens_a_still_valid_quotation(): void
    {
        [$organization, $owner] = $this->businessWith();
        $oven = $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '3', 'price' => '200000']]);
        app(QuotationService::class)->accept($quotation, $owner, $organization);
        $context = PricingContext::forOrganization($organization, $owner->id);
        app(CartService::class)->addQuotation($cart = app(CartService::class)->forUser($owner), $quotation->fresh(), $context);
        $order = $this->place($cart, PaymentMethod::BankTransfer, $owner, $context)->order;
        $this->assertSame(7, $oven->fresh()->stock_quantity);

        app(OrderService::class)->cancel($order, 'Changed plans', $owner, byCustomer: true);

        $this->assertSame(OrderStatus::Cancelled, $order->fresh()->status);
        $this->assertSame(10, $oven->fresh()->stock_quantity, 'Quoted stock is restocked through the ledger.');
        $this->assertSame(QuotationStatus::Accepted, $quotation->fresh()->status);
        $this->assertNull($quotation->fresh()->order_id);
    }

    public function test_viewers_and_resellers_cannot_act_on_quotations(): void
    {
        [$organization, $owner] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);
        [, $reseller] = $this->resellerWith();
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '1', 'price' => '50000']]);

        $this->actingAs($viewer)->get(route('business.quotations.show', $quotation))->assertOk()->assertDontSee('Accept quote');
        $this->actingAs($viewer)->post(route('business.quotations.accept', $quotation))->assertForbidden();
        $this->actingAs($viewer)->post(route('business.quotations.decline', $quotation))->assertForbidden();
        $this->actingAs($reseller)->get(route('business.quotations.show', $quotation))->assertForbidden();
        $this->assertSame(QuotationStatus::Quoted, $quotation->fresh()->status);

        $this->actingAs($owner)->post(route('business.quotations.decline', $quotation), ['reason' => 'Too expensive'])->assertSessionHasNoErrors();
        $this->assertSame(QuotationStatus::Declined, $quotation->fresh()->status);
        $this->assertSame('Too expensive', AuditLog::query()->where('action', 'quotation.declined')->sole()->reason);
    }

    public function test_staff_quotation_screens_need_the_quotation_permission(): void
    {
        [$organization, $owner] = $this->businessWith();
        $quotation = app(QuotationService::class)->request(['contact_name' => 'O', 'contact_phone' => '01711111111', 'contact_email' => null, 'request_text' => 'x', 'customer_note' => null], $owner, $organization);
        $role = Role::create(['name' => 'orders_only', 'label' => 'Orders only']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'orders.manage'])->pluck('id'));
        $clerk = User::factory()->withMfa()->withRole('orders_only')->create();

        $this->actingAsStaff($clerk)->get('/admin/quotations')->assertForbidden();
        $this->actingAsStaff($clerk)->post(route('admin.quotations.close', $quotation), ['reason' => 'nope'])->assertForbidden();
        $this->actingAsStaff($clerk)->get('/admin/organizations')->assertForbidden();

        foreach (['/admin/quotations', '/admin/quotations?status=requested', route('admin.quotations.show', $quotation), '/admin/organizations', route('admin.organizations.show', $organization)] as $url) {
            $this->actingAsStaff($this->admin)->get($url)->assertOk();
        }
    }

    private function expectsForeignAdd(Quotation $quotation, Organization $other, User $otherOwner): void
    {
        try {
            app(CartService::class)->addQuotation(app(CartService::class)->forUser($otherOwner), $quotation->fresh(), PricingContext::forOrganization($other, $otherOwner->id));
            $this->fail('Another organization must not add this quotation.');
        } catch (ValidationException) {
            $this->assertSame(0, CartItem::query()->whereNotNull('quotation_id')->count());
        }
    }
}

<?php

namespace Tests\Feature;

use App\Enums\LeadStage;
use App\Enums\PaymentMethod;
use App\Enums\PriceListAudience;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\User;
use App\Services\Cart\CartService;
use App\Services\Orders\OrderService;
use App\Services\Payments\PaymentService;
use App\Services\Pricing\PricingContext;
use App\Services\Quotations\QuotationService;
use App\Services\Reports\ReportService;
use Carbon\CarbonImmutable;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Reports are computed from real records with explicit definitions. Every expected number here is
 * worked out by hand from the orders the test places.
 */
class ReportsTest extends TestCase
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

    /**
     * A: retail COD, 2 × ৳1,000 with 10% coupon → 2,000 − 200 + 80 delivery = ৳1,880 (unpaid)
     * B: retail bKash, 1 × ৳500 + 80 = ৳580, paid, then ৳100 refunded
     * C: business COD, 1 × ৳800 business tier + 80 = ৳880 (unpaid)
     * D: retail COD ৳1,000 + 80 = ৳1,080, cancelled
     */
    private function scenario(): void
    {
        $pan = $this->unitVariant(100000, 50, 'PAN');
        $pot = $this->unitVariant(50000, 50, 'POT');
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($pan, [[1, 80000]])->create();
        Promotion::factory()->coupon('SAVE10')->percent(1000)->create(['name' => 'Ten percent']);

        $alice = User::factory()->create(['name' => 'Alice']);
        $cart = $this->cartWith([[$pan, 2]], $alice);
        app(CartService::class)->setCoupon($cart, 'SAVE10');
        $this->place($cart, PaymentMethod::CashOnDelivery, $alice);

        $bob = User::factory()->create(['name' => 'Bob']);
        $paidOrder = $this->place($this->cartWith([[$pot, 1]], $bob), PaymentMethod::Bkash, $bob)->order;
        $this->sendCallback($this->payment($paidOrder))->assertOk();
        app(PaymentService::class)->refund($paidOrder->fresh(), 10000, 'One item damaged', $this->admin);

        [$organization, $owner] = $this->businessWith();
        $this->place($this->cartWith([[$pan, 1]], $owner), PaymentMethod::CashOnDelivery, $owner, PricingContext::forOrganization($organization, $owner->id));

        $cancelled = $this->place($this->cartWith([[$pan, 1]], $alice), PaymentMethod::CashOnDelivery, $alice)->order;
        app(OrderService::class)->cancel($cancelled, 'Duplicate order', $alice, byCustomer: true);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now('Asia/Dhaka');
    }

    public function test_financial_figures_separate_gross_discount_delivery_refunds_and_net(): void
    {
        $this->scenario();

        $financial = app(ReportService::class)->financial($this->today(), $this->today());

        $this->assertSame([
            'orders' => 3,
            'gross' => 330000,
            'discounts' => 20000,
            'net_sales' => 310000,
            'delivery' => 24000,
            'billed' => 334000,
            'refunds' => 10000,
            'net_revenue' => 324000,
            'paid' => 58000,
            'unpaid' => 276000,
            'cancelled_orders' => 1,
            'cancelled_amount' => 108000,
            'average_order' => 111333,
        ], $financial);

        $yesterday = $this->today()->subDay();
        $this->assertSame(0, app(ReportService::class)->financial($yesterday, $yesterday)['orders'], 'Only orders placed in the range count.');
    }

    public function test_breakdowns_by_status_audience_products_categories_and_promotions(): void
    {
        $this->scenario();
        $reports = app(ReportService::class);
        $day = $this->today();

        $statuses = collect($reports->byStatus($day, $day))->mapWithKeys(fn ($row) => [$row['status']->value => $row['orders']]);
        $this->assertSame(3, $statuses['confirmed']);
        $this->assertSame(1, $statuses['cancelled']);

        $audiences = collect($reports->byAudience($day, $day))->keyBy('audience');
        $this->assertSame([2, 246000], [$audiences['retail']['orders'], $audiences['retail']['billed']]);
        $this->assertSame([1, 88000], [$audiences['business']['orders'], $audiences['business']['billed']]);
        $this->assertSame(0, $audiences['reseller']['orders']);

        $products = $reports->topProducts($day, $day);
        $this->assertSame(['PAN', 3, 280000, 2], [$products[0]['sku'], $products[0]['quantity'], $products[0]['revenue'], $products[0]['orders']], 'Cancelled order D is excluded.');
        $this->assertSame(['POT', 50000], [$products[1]['sku'], $products[1]['revenue']]);
        $this->assertSame(280000, $reports->topCategories($day, $day)[0]['revenue']);

        $this->assertSame([['name' => 'Ten percent', 'code' => 'SAVE10', 'uses' => 1, 'discount' => 20000, 'billed' => 188000]], $reports->promotions($day, $day));

        $daily = $reports->daily($day->subDays(29), $day);
        $this->assertCount(30, $daily);
        $this->assertSame(['orders' => 3, 'billed' => 334000], array_intersect_key(end($daily), ['orders' => 0, 'billed' => 0]));
    }

    public function test_quotation_lines_count_under_their_products(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '2', 'price' => '150000'], ['description' => 'Installation', 'quantity' => '1', 'price' => '5000']], $this->admin);
        app(QuotationService::class)->accept($quotation, $owner, $organization);
        $context = PricingContext::forOrganization($organization, $owner->id);
        app(CartService::class)->addQuotation($cart = app(CartService::class)->forUser($owner), $quotation->fresh(), $context);
        $this->place($cart, PaymentMethod::BankTransfer, $owner, $context);

        $products = app(ReportService::class)->topProducts($this->today(), $this->today());
        $this->assertSame([['OVEN', 2, 15000000]], array_map(fn ($row) => [$row['sku'], $row['quantity'], $row['revenue']], $products), 'Custom (non-catalog) lines are not products.');

        $rfq = app(ReportService::class)->rfq($this->today(), $this->today());
        $this->assertSame([1, 1, 1, 1, 100], [$rfq['requested'], $rfq['quoted'], $rfq['accepted'], $rfq['ordered'], $rfq['conversion']]);
        $this->assertSame(15500000, $rfq['ordered_value']);
        $this->assertSame(1, collect($rfq['pipeline'])->firstWhere('stage', LeadStage::Won)['leads']);
    }

    public function test_repeat_purchasing_and_acquisition(): void
    {
        $pan = $this->unitVariant(100000, 50, 'PAN');
        $loyal = User::factory()->create();
        $this->place($this->cartWith([[$pan, 1]], $loyal), user: $loyal);
        $this->travel(10)->days();
        $this->place($this->cartWith([[$pan, 1]], $loyal), user: $loyal);
        $newcomer = User::factory()->create();
        $this->place($this->cartWith([[$pan, 1]], $newcomer), user: $newcomer);
        $this->place($this->cartWith([[$pan, 1]]));

        $customers = app(ReportService::class)->customers($this->today()->subDays(2), $this->today());

        $this->assertSame(2, $customers['buyers']);
        $this->assertSame(1, $customers['first_time'], 'The loyal customer first bought before the range.');
        $this->assertSame(1, $customers['repeat']);
        $this->assertSame(50, $customers['repeat_rate']);
        $this->assertSame(1, $customers['guest_orders']);
        $this->assertSame(1, $customers['new_accounts'], 'Only the newcomer joined in the range (staff excluded).');
    }

    public function test_inventory_lists_low_and_out_of_stock(): void
    {
        $low = $this->unitVariant(100000, 3, 'LOW');
        $low->forceFill(['low_stock_threshold' => 5])->save();
        $this->unitVariant(100000, 0, 'OUT');
        $this->unitVariant(100000, 50, 'FINE');

        $inventory = app(ReportService::class)->inventory();
        $this->assertSame(['LOW'], array_map(fn ($variant) => $variant->sku, $inventory['low']));
        $this->assertSame(['OUT'], array_map(fn ($variant) => $variant->sku, $inventory['out']));

        $this->actingAsStaff($this->admin)->get('/admin/reports/inventory')->assertOk()->assertSee('LOW')->assertSee('OUT')->assertDontSee('FINE');
    }

    public function test_every_report_page_renders_and_exports_csv(): void
    {
        $this->scenario();

        foreach (['overview', 'products', 'inventory', 'promotions', 'customers', 'rfq'] as $section) {
            $this->actingAsStaff($this->admin)->get("/admin/reports/{$section}?range=7d")->assertOk();
            $this->actingAsStaff($this->admin)->get("/admin/reports/{$section}?range=7d&format=csv")->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        }
        $this->actingAsStaff($this->admin)->get('/admin/reports')->assertSee('৳3,240', false)->assertSee('Net revenue');

        $csv = $this->actingAsStaff($this->admin)->get('/admin/reports?range=today&format=csv')->streamedContent();
        $this->assertStringContainsString('"net revenue",3240', $csv);
        $this->assertStringContainsString('gross,3300', $csv);
        $this->actingAsStaff($this->admin)->get('/admin/reports/unknown')->assertNotFound();
        $this->actingAsStaff($this->admin)->get('/admin/reports?range=custom&from=2026-99-99&to=garbage')->assertOk();
    }

    public function test_operational_report_access_hides_money_and_others_are_refused(): void
    {
        $this->scenario();
        $role = Role::create(['name' => 'analyst', 'label' => 'Ops analyst']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'reports.view'])->pluck('id'));
        $analyst = User::factory()->withMfa()->withRole('analyst')->create();

        $this->actingAsStaff($analyst)->get('/admin/reports')->assertOk()->assertSee('Money figures are hidden')->assertDontSee('৳3,240', false)->assertDontSee('Net revenue');
        $this->actingAsStaff($analyst)->get('/admin/reports/products')->assertOk()->assertSee('PAN')->assertDontSee('৳2,800', false);
        $this->actingAsStaff($analyst)->get('/admin/reports/promotions')->assertForbidden();
        $csv = $this->actingAsStaff($analyst)->get('/admin/reports?format=csv')->streamedContent();
        $this->assertStringNotContainsString('BDT', $csv);

        $crmOnly = Role::create(['name' => 'crm_only', 'label' => 'CRM only']);
        $crmOnly->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'crm.access'])->pluck('id'));
        $this->actingAsStaff(User::factory()->withMfa()->withRole('crm_only')->create())->get('/admin/reports')->assertForbidden();
        $this->actingAs(User::factory()->create())->get('/admin/reports')->assertForbidden();
    }
}

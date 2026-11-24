<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Models\Cart;
use App\Models\Setting;
use App\Services\Orders\OrderService;
use App\Services\Pricing\PricingContext;
use App\Support\CsvDownload;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Dashboard, statements, invoices, reorder, saved addresses and the public B2B pages.
 */
class PortalPagesTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_statement_lists_the_month_with_charged_paid_and_balance(): void
    {
        [$organization, $owner] = $this->businessWith();
        $context = PricingContext::forOrganization($organization, $owner->id);
        $first = $this->place($this->cartWith([[$this->unitVariant(100000, 10, 'PAN'), 1]], $owner), PaymentMethod::CashOnDelivery, $owner, $context)->order;
        $second = $this->place($this->cartWith([[$this->unitVariant(50000, 10, 'POT'), 1]], $owner), PaymentMethod::CashOnDelivery, $owner, $context)->order;
        app(OrderService::class)->cancel($second, 'Not needed', $owner, byCustomer: true);

        $page = $this->actingAs($owner)->get('/business/statements')->assertOk()
            ->assertSee($first->number)->assertSee($second->number);
        $this->assertSame(108000, $page->viewData('statement')['totals']['charged'], 'Cancelled orders are not charged.');
        $this->assertSame(108000, $page->viewData('statement')['totals']['balance'], 'COD not yet collected.');

        $csv = $this->actingAs($owner)->get('/business/statements?format=csv&month='.now('Asia/Dhaka')->format('Y-m'))->assertOk()->streamedContent();
        $this->assertStringContainsString($first->number, $csv);
        $this->assertStringContainsString('Total,,,1080,0,0,1080', $csv);

        $this->actingAs($owner)->get('/business/statements?month=1999-01')->assertOk()->assertSee('No orders this month');
        $this->actingAs($owner)->get('/business/statements?month=garbage')->assertOk();
    }

    public function test_csv_cells_that_look_like_formulas_are_neutralised(): void
    {
        $this->assertSame("'=HYPERLINK(\"x\")", CsvDownload::cell('=HYPERLINK("x")'));
        $this->assertSame("'+1", CsvDownload::cell('+1'));
        $this->assertSame("'@SUM(A1)", CsvDownload::cell('@SUM(A1)'));
        $this->assertSame('BH-ING-0001', CsvDownload::cell('BH-ING-0001'));
    }

    public function test_reorder_adds_past_lines_at_todays_prices(): void
    {
        [$organization, $owner] = $this->businessWith();
        $pan = $this->unitVariant(100000, 10, 'PAN');
        $order = $this->place($this->cartWith([[$pan, 2]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id))->order;
        $pan->forceFill(['price_paisa' => 120000])->save();

        $this->actingAs($owner)->post(route('business.orders.reorder', $order))->assertRedirect(route('cart.show'));

        $cart = Cart::query()->where('user_id', $owner->id)->sole();
        $this->assertSame(2, $cart->items()->sole()->quantity);
        $this->actingAs($owner)->get('/cart')->assertSee('৳2,400', false)->assertDontSee('৳2,000', false);
    }

    public function test_saved_addresses_prefill_checkout_for_that_organization_only(): void
    {
        [$organization, $owner] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);
        $data = ['label' => 'Gulshan kitchen', 'recipient_name' => 'Head Chef', 'phone' => '+8801911222333', 'line' => 'House 5, Road 7', 'area' => 'Gulshan', 'district' => 'Dhaka'];

        $this->actingAs($viewer)->post('/business/addresses', $data)->assertForbidden();
        $this->actingAs($owner)->post('/business/addresses', ['phone' => '123'] + $data)->assertSessionHasErrors('phone');
        $this->actingAs($owner)->post('/business/addresses', $data)->assertSessionHasNoErrors();
        $address = $organization->addresses()->sole();
        $this->assertSame('01911222333', $address->phone);
        $this->assertTrue($address->is_default, 'The first address becomes the default.');

        $this->cartWith([[$this->unitVariant(), 1]], $owner);
        $this->actingAs($owner)->get('/business/dashboard');
        $this->actingAs($owner)->get('/checkout')->assertOk()
            ->assertSee('You are ordering for Test Restaurant')
            ->assertSee('value="Head Chef"', false)->assertSee('value="House 5, Road 7"', false);

        [, $stranger] = $this->businessWith(name: 'Other Restaurant');
        $this->cartWith([[$this->unitVariant(5000, 5, 'S1'), 1]], $stranger);
        $this->actingAs($stranger)->get('/checkout?address='.$address->id)->assertOk()->assertDontSee('House 5, Road 7');
    }

    public function test_dashboard_shows_this_organizations_numbers_and_waiting_quotes(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->unitVariant(100000, 10, 'OVEN');
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'OVEN', 'quantity' => '1', 'price' => '90000']]);
        $this->place($this->cartWith([[$this->unitVariant(50000, 10, 'POT'), 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id));

        $page = $this->actingAs($owner)->get('/business/dashboard')->assertOk()
            ->assertSee($quotation->number)->assertSee('৳90,000', false);
        $kpis = collect($page->viewData('kpis'))->pluck('value', 'label');
        $this->assertSame(1, $kpis['Orders this month']);
        $this->assertSame(58000, $kpis['Spent this month']);
        $this->assertSame(1, $kpis['Open quotations']);
    }

    public function test_public_landing_pages_product_rfq_form_and_sitemap(): void
    {
        $this->get('/business')->assertOk()->assertSee('Business pricing, bulk orders')->assertSee('name="request_text"', false)
            ->assertSee('images/contextual/business-kitchen-line-1600.webp', false)->assertSee('alt="Restaurant kitchen line with pendant lights, hanging pans and shelves of plates and serving ware"', false);
        // The licensed contextual photo is optional: without the file the labelled placeholder returns.
        $publicPath = public_path();
        $this->app->usePublicPath(sys_get_temp_dir());
        $this->get('/business')->assertOk()->assertSee('Licensed photo pending')->assertDontSee('class="scene-photo"', false);
        $this->app->usePublicPath($publicPath);
        $this->get('/reseller')->assertOk()->assertSee('Reseller rate card')->assertDontSee('name="request_text"', false);
        $this->get('/sitemap.xml')->assertOk()->assertSee(route('business.landing'))->assertSee(route('reseller.landing'));

        $variant = $this->unitVariant(100000, 10, 'PAN');
        $this->get('/product/'.$variant->product->slug)->assertOk()->assertSee('action="'.route('quotations.store').'"', false)->assertSee('(PAN) ×');
    }

    public function test_portal_renders_in_bangla_with_western_digits(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        Setting::put(Setting::LANGUAGE_DEFAULT, 'bn');
        [$organization, $owner] = $this->businessWith();
        $this->place($this->cartWith([[$this->unitVariant(100000, 10, 'PAN'), 1]], $owner), user: $owner, context: PricingContext::forOrganization($organization, $owner->id));

        $this->actingAs($owner)->get('/business/dashboard')->assertOk()
            ->assertSee('বিজনেস ড্যাশবোর্ড')->assertSee('বাল্ক অর্ডার')->assertSee('৳1,080', false);
        $this->actingAs($owner)->get('/business/team')->assertOk()->assertSee('টিম মেম্বার');
    }
}

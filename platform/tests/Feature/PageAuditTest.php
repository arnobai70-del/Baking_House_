<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Enums\PaymentMethod;
use App\Models\Bundle;
use App\Models\Category;
use App\Models\CrmLead;
use App\Models\Order;
use App\Models\Page;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Promotion;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Services\Pricing\PricingContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\Support\CommerceHelpers;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Page-by-page QA: every storefront, customer, portal and admin page renders for the role that
 * owns it and passes automated accessibility basics (lang, title, one h1, image alt text, form
 * labels, accessible names for links / buttons, unique ids). Also guards query counts.
 */
class PageAuditTest extends TestCase
{
    use CommerceHelpers, PortalHelpers, RefreshDatabase;

    /** @var list<string> */
    private array $problems = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->zones();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    private function audit(TestResponse $response, string $url): void
    {
        $response->assertOk();
        $html = (string) $response->getContent();
        if (! str_contains($response->headers->get('Content-Type', ''), 'html')) {
            return;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8"?>'.$html);
        libxml_clear_errors();
        $xpath = new DOMXPath($dom);
        $fail = function (string $message) use ($url): void {
            $this->problems[] = "{$url}: {$message}";
        };

        if (trim((string) $xpath->evaluate('string(/html/@lang)')) === '') {
            $fail('missing <html lang>');
        }
        if (trim((string) $xpath->evaluate('string(//title)')) === '') {
            $fail('missing <title>');
        }
        $h1 = $xpath->query('//h1')->length;
        if ($h1 !== 1) {
            $fail("{$h1} <h1> elements");
        }
        foreach ($xpath->query('//img[not(@alt)]') as $img) {
            $fail('image without alt: '.$img->getAttribute('src'));
        }

        $ids = [];
        foreach ($xpath->query('//*[@id]') as $element) {
            $id = $element->getAttribute('id');
            if (isset($ids[$id])) {
                $fail("duplicate id \"{$id}\"");
            }
            $ids[$id] = true;
        }

        foreach ($xpath->query('//input[not(@type="hidden") and not(@type="submit") and not(@type="button")] | //select | //textarea') as $field) {
            /** @var DOMElement $field */
            if ($field->getAttribute('tabindex') === '-1' || $this->hasAncestor($field, 'label')) {
                continue;
            }
            $id = $field->getAttribute('id');
            $labelled = ($id !== '' && $xpath->query("//label[@for='{$id}']")->length > 0)
                || $field->getAttribute('aria-label') !== '' || $field->getAttribute('aria-labelledby') !== '';
            if (! $labelled) {
                $fail('form field without label: '.$field->getAttribute('name'));
            }
        }

        foreach ($xpath->query('//a[@href] | //button | //summary') as $control) {
            /** @var DOMElement $control */
            $name = trim($control->textContent).$control->getAttribute('aria-label').$control->getAttribute('title');
            foreach ($xpath->query('.//img[@alt]', $control) as $img) {
                $name .= $img->getAttribute('alt');
            }
            if (trim($name) === '' && $control->getAttribute('aria-hidden') !== 'true' && $control->getAttribute('tabindex') !== '-1') {
                $fail('control without accessible name: <'.$control->nodeName.' '.($control->getAttribute('href') ?: $control->getAttribute('class')).'>');
            }
        }
    }

    private function hasAncestor(DOMElement $element, string $tag): bool
    {
        for ($node = $element->parentNode; $node instanceof DOMElement; $node = $node->parentNode) {
            if ($node->nodeName === $tag) {
                return true;
            }
        }

        return false;
    }

    public function test_every_page_renders_for_its_role_and_passes_accessibility_basics(): void
    {
        /* Catalog + commerce fixtures covering every page type. */
        $pan = $this->unitVariant(100000, 20, 'PAN');
        $choc = $this->weightVariant(65000, 50000, 'CHOC');
        $bundle = Bundle::factory()->withItems([[$pan, 1]])->create();
        Promotion::factory()->coupon('SAVE10')->create();
        $category = Category::query()->findOrFail($pan->product->primary_category_id);
        Page::query()->create(['slug' => 'about-us', 'title' => 'About Baking House', 'body' => "## Who we are\n\nTest content."])->forceFill(['is_published' => true])->save();

        [$organization, $owner] = $this->businessWith();
        [$resellerOrganization, $resellerOwner] = $this->resellerWith();
        $retail = User::factory()->create();
        $retailOrder = $this->place($this->cartWith([[$pan, 1]], $retail), PaymentMethod::CashOnDelivery, $retail)->order;
        $businessOrder = $this->place($this->cartWith([[$choc, 1000]], $owner), PaymentMethod::BankTransfer, $owner, PricingContext::forOrganization($organization, $owner->id))->order;
        $this->place($this->cartWith([[$pan, 1]], $resellerOwner), PaymentMethod::CashOnDelivery, $resellerOwner, PricingContext::forOrganization($resellerOrganization, $resellerOwner->id));
        $superAdmin = User::factory()->superAdmin()->create();
        $quotation = $this->issuedQuotation($organization, $owner, [['sku' => 'PAN', 'quantity' => '2', 'price' => '1800']], $superAdmin);
        $lead = CrmLead::query()->firstOrFail();
        app(CrmService::class)->addTask($lead, 'Call back', now()->addDay(), null, $superAdmin);
        $priceList = PriceList::factory()->contractFor($organization)->withTiers($pan, [[1, 90000]])->create();
        $image = ProductImage::factory()->create(['product_id' => $pan->product_id]);
        $this->cartWith([[$pan, 1]], $retail);

        $guest = ['/', '/shop', $category->url(), $pan->product->url(), $choc->product->url(), '/search?q=pan', '/combos', '/combo/'.$bundle->slug,
            '/business', '/reseller', '/cart', '/login', '/register', '/contact', '/order/track', '/page/about-us'];
        foreach ($guest as $url) {
            $this->audit($this->get($url), "guest {$url}");
        }

        foreach (['/account', '/account/profile', '/apply/business', '/apply/reseller', '/checkout', route('orders.show', $retailOrder)] as $url) {
            $this->audit($this->actingAs($retail)->get($url), "retail {$url}");
        }

        foreach (['dashboard', 'orders', 'bulk', 'quotations', 'quotations/'.$quotation->number, 'prices', 'addresses', 'team', 'statements', 'orders/'.$businessOrder->number.'/invoice'] as $page) {
            $this->audit($this->actingAs($owner)->get("/business/{$page}"), "business /business/{$page}");
        }
        foreach (['dashboard', 'orders', 'bulk', 'prices', 'addresses', 'team', 'statements'] as $page) {
            $this->audit($this->actingAs($resellerOwner)->get("/reseller/{$page}"), "reseller /reseller/{$page}");
        }

        $admin = ['/admin', '/admin/orders', route('admin.orders.show', $businessOrder), '/admin/orders/reconciliation', '/admin/zones', '/admin/zones/create',
            '/admin/products', '/admin/products/create', route('admin.products.edit', $pan->product), route('admin.media.product', $pan->product), '/admin/media',
            '/admin/categories', '/admin/categories/create', route('admin.categories.edit', $category), '/admin/brands',
            '/admin/price-lists', '/admin/price-lists/create', route('admin.price-lists.edit', $priceList), '/admin/scheduled-prices', '/admin/price-simulator',
            '/admin/promotions', '/admin/promotions/create', '/admin/bundles', '/admin/bundles/create', route('admin.bundles.edit', $bundle),
            '/admin/inventory', route('admin.inventory.show', $pan), '/admin/inventory/verify',
            '/admin/quotations', route('admin.quotations.show', $quotation), '/admin/organizations', route('admin.organizations.show', $organization),
            '/admin/crm', '/admin/crm/leads', route('admin.crm.leads.show', $lead), '/admin/crm/customers', route('admin.crm.customers.show', $retail),
            route('admin.crm.organizations.show', $organization), '/admin/crm/tasks',
            '/admin/reports', '/admin/reports/products', '/admin/reports/inventory', '/admin/reports/promotions', '/admin/reports/customers', '/admin/reports/rfq',
            '/admin/settings/storefront', '/admin/pages', '/admin/pages/create', '/admin/staff', '/admin/audit', '/account/security'];
        foreach ($admin as $url) {
            $this->audit($this->actingAsStaff($superAdmin)->get($url), "admin {$url}");
        }

        $this->assertNotNull($image);
        $this->assertSame([], $this->problems, implode("\n", $this->problems));
    }

    public function test_catalog_pages_use_a_bounded_number_of_queries(): void
    {
        foreach (range(1, 8) as $i) {
            $this->unitVariant(100000, 5, 'SKU'.$i);
        }
        $this->get('/shop')->assertOk();

        $count = 0;
        DB::listen(function () use (&$count): void {
            $count++;
        });
        $this->get('/shop')->assertOk();
        $shopQueries = $count;

        foreach (range(9, 20) as $i) {
            $this->unitVariant(100000, 5, 'SKU'.$i);
        }
        $count = 0;
        $this->get('/shop')->assertOk();

        $this->assertLessThanOrEqual(20, $shopQueries, 'Shop page query budget.');
        $this->assertSame($shopQueries, $count, 'Query count must not grow with the number of products or categories (no N+1).');
    }

    public function test_bangla_file_uses_western_digits_and_keeps_placeholders(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);
        $problems = [];
        foreach ($translations as $key => $value) {
            if (preg_match('/[০-৯]/u', $value) === 1) {
                $problems[] = "Bangla digits in: {$key}";
            }
            preg_match_all('/:[a-z_]+/', $key, $expected);
            preg_match_all('/:[a-z_]+/', $value, $actual);
            sort($expected[0]);
            sort($actual[0]);
            if ($expected[0] !== $actual[0]) {
                $problems[] = "Placeholder mismatch in: {$key}";
            }
        }

        $this->assertSame([], $problems);
    }

    public function test_orders_without_users_are_counted_once(): void
    {
        $this->place($this->cartWith([[$this->unitVariant(), 1]]));
        $this->assertSame(1, Order::query()->count());
        $this->assertSame(1, Product::query()->count());
        $this->assertSame(0, Quotation::query()->count());
        $this->assertNotNull(OrganizationRole::Owner);
    }
}

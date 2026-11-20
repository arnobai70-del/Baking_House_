<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\Category;
use App\Models\Organization;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Setting;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Storefront shows each buyer only their own prices; Bangla/English is admin-configurable and
 * uses the same product rows.
 */
class StorefrontPricingAndLanguageTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->withWeightVariant(65000, 50000, ['sku' => 'CHOC'])->create([
            'name' => 'Dark Chocolate', 'name_bn' => 'ডার্ক চকলেট', 'short_description' => 'For coating', 'short_description_bn' => 'কোটিংয়ের জন্য',
        ]);
        $this->variant = $this->product->variants()->sole();
        PriceList::factory()->audience(PriceListAudience::Business)->withTiers($this->variant, [[5000, 61234]])->create();
        PriceList::factory()->audience(PriceListAudience::Reseller)->withTiers($this->variant, [[1000, 62345]])->create();
    }

    public function test_guest_page_never_contains_trade_prices(): void
    {
        $html = $this->get("/product/{$this->product->slug}")->assertOk()->getContent();

        $this->assertStringNotContainsString('61234', $html);
        $this->assertStringNotContainsString('62345', $html);
        $this->assertStringNotContainsString('612.34', $html);
    }

    public function test_business_member_sees_only_business_tiers(): void
    {
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();

        $html = $this->actingAs($owner)->get("/product/{$this->product->slug}")->assertOk()->getContent();

        $this->assertStringContainsString('Your business price', $html);
        $this->assertStringContainsString('৳612.34', $html);
        $this->assertStringContainsString('61234', $html, 'Business tier is in the calculator data.');
        $this->assertStringNotContainsString('62345', $html, 'Reseller tier never leaks to business.');
    }

    public function test_reseller_member_sees_only_reseller_tiers(): void
    {
        $reseller = User::factory()->create();
        Organization::factory()->reseller()->approved()->withMember($reseller)->create();

        $html = $this->actingAs($reseller)->get("/product/{$this->product->slug}")->getContent();

        $this->assertStringContainsString('Your reseller price', $html);
        $this->assertStringNotContainsString('61234', $html);
    }

    public function test_minimum_order_is_shown_for_unit_products(): void
    {
        $pan = Product::factory()->withFixedVariant(245000, 100, ['min_order_quantity' => 6])->create();

        $this->get("/product/{$pan->slug}")->assertSee('Minimum order');
    }

    public function test_language_switch_is_hidden_until_admin_enables_bangla(): void
    {
        $this->get('/')->assertDontSee('বাংলা');
        $this->post('/language', ['locale' => 'bn'])->assertSessionHasErrors('locale');
    }

    public function test_admin_enables_bangla_and_shoppers_can_switch(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $admin = User::factory()->admin()->create();

        $this->actingAsStaff($admin)->put('/admin/settings/storefront', ['languages' => ['en', 'bn'], 'default' => 'en'])->assertRedirect();
        $this->assertSame(['en', 'bn'], Setting::enabledLanguages());
        $this->assertDatabaseHas('audit_logs', ['action' => 'settings.languages_updated']);
        $this->post('/logout');
        Setting::flushMemo();

        $this->get('/')->assertSee('বাংলা')->assertSee('Shop by category');

        $this->post('/language', ['locale' => 'bn'])->assertRedirect();
        $page = $this->get("/product/{$this->product->slug}")->assertOk();

        $page->assertSee('<html lang="bn">', false)
            ->assertSee('ডার্ক চকলেট')
            ->assertSee('কোটিংয়ের জন্য')
            ->assertSee('কার্টে যোগ করুন')
            ->assertSee('Noto+Sans+Bengali', false);
        $this->assertSame(1, Product::query()->count(), 'Translations never duplicate product records.');
    }

    public function test_bangla_falls_back_to_english_when_not_translated(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        Setting::put(Setting::LANGUAGE_DEFAULT, 'bn');
        $plain = Product::factory()->withFixedVariant()->create(['name' => 'Steel Wok']);
        $category = Category::query()->find($plain->primary_category_id);

        $this->get("/product/{$plain->slug}")->assertSee('Steel Wok')->assertSee($category->name);
    }

    public function test_default_language_setting_applies_to_new_visitors(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        Setting::put(Setting::LANGUAGE_DEFAULT, 'bn');

        $this->get('/')->assertSee('<html lang="bn">', false)->assertSee('ক্যাটাগরি অনুযায়ী কিনুন');
    }

    public function test_every_storefront_string_has_a_bangla_translation(): void
    {
        $translations = json_decode((string) file_get_contents(lang_path('bn.json')), true, flags: JSON_THROW_ON_ERROR);
        /* Every customer-facing view (storefront, checkout, orders, business & reseller portals) and
           the customer messages raised by portal / quotation code. Admin screens stay English. */
        $files = array_merge(
            glob(resource_path('views/storefront/*.blade.php')),
            glob(resource_path('views/storefront/*/*.blade.php')),
            glob(resource_path('views/portal/*.blade.php')),
            glob(resource_path('views/portal/*/*.blade.php')),
            glob(resource_path('views/components/*.blade.php')),
            [resource_path('views/layouts/app.blade.php')],
            glob(app_path('Services/Quotations/*.php')),
            glob(app_path('Services/Portal/*.php')),
            glob(app_path('Http/Controllers/Portal/*.php')),
            [app_path('Http/Controllers/Storefront/QuotationRequestController.php'), app_path('Http/Controllers/InvitationController.php'), app_path('Support/QuantityInput.php')],
        );

        $missing = [];
        foreach ($files as $file) {
            preg_match_all("/__\\('((?:[^'\\\\]|\\\\.)*)'/", (string) file_get_contents($file), $matches);
            foreach ($matches[1] as $key) {
                $key = stripslashes($key);
                if (! array_key_exists($key, $translations) && ! str_ends_with($key, ' ') && ! str_starts_with($key, 'Your ')) {
                    $missing[] = $key;
                }
            }
        }

        $this->assertSame([], array_values(array_unique($missing)));
    }
}

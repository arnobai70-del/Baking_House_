<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Rebrand to NadimShop.com: one configurable name (APP_NAME → brand.name / seo.site_name), no hard-coded
 * domain, and no trace of the former name on customer-facing pages.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_carry_the_configured_brand_and_not_the_former_name(): void
    {
        $product = Product::factory()->withFixedVariant()->create();

        foreach (['/', '/shop', $product->url(), '/contact', '/login'] as $path) {
            $this->get($path)->assertOk()
                ->assertSee(config('brand.name'))
                ->assertSee(config('brand.name').'</title>', false)
                ->assertDontSee('Baking House')
                ->assertSee('favicon.svg', false);
        }
        $this->assertSame('NadimShop.com', config('brand.name'), 'Default brand follows APP_NAME.');
        $this->get('/')->assertSee('"name":"'.config('seo.site_name').'"', false);
    }

    public function test_the_brand_is_configurable_and_the_domain_comes_from_app_url(): void
    {
        config(['brand.name' => 'Test Brand Shop', 'seo.site_name' => 'Test Brand Shop']);

        $this->get('/')->assertOk()->assertSee('Test Brand Shop</strong>', false)->assertSee('© '.date('Y').' Test Brand Shop', false);
        $this->get('/')->assertDontSee('nadimshop.com/', false);

        config(['security.staging_gate' => ['enabled' => true, 'user' => 'tester', 'password_hash' => Hash::make('x'), 'open_paths' => ['up']]]);
        $this->get('/')->assertStatus(401)->assertHeader('WWW-Authenticate', 'Basic realm="Test Brand Shop staging", charset="UTF-8"');
    }

    public function test_bangla_pages_keep_the_brand_in_latin_letters(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);

        $this->get('/bn')->assertOk()->assertSee(config('brand.name'))->assertSee(config('brand.name').' হোম', false)->assertDontSee('Baking House');
    }
}

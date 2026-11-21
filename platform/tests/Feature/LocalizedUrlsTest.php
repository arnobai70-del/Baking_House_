<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use App\Models\Setting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Language-prefixed public URLs (D-059): /bn/… for Bangla, hreflang alternates, bilingual sitemap.
 */
class LocalizedUrlsTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->product = Product::factory()->withFixedVariant()->create(['name' => 'Steel Wok', 'name_bn' => 'স্টিলের কড়াই']);
    }

    /**
     * Expected absolute URL from the configured root (url() itself follows the current page language).
     */
    private function u(string $path): string
    {
        return rtrim((string) config('app.url'), '/').$path;
    }

    private function enableBangla(): void
    {
        Setting::put(Setting::LANGUAGES_ENABLED, ['en', 'bn']);
        Setting::put(Setting::LANGUAGE_DEFAULT, 'en');
    }

    public function test_bangla_urls_do_not_exist_until_bangla_is_enabled(): void
    {
        $this->get('/bn')->assertNotFound();
        $this->get("/bn/product/{$this->product->slug}")->assertNotFound();

        $this->get("/product/{$this->product->slug}")->assertOk()->assertDontSee('hreflang', false);
    }

    public function test_the_url_decides_the_language_and_links_stay_in_it(): void
    {
        $this->enableBangla();
        $slug = $this->product->slug;
        $category = Category::query()->findOrFail($this->product->primary_category_id);

        $this->get("/product/{$slug}")->assertOk()->assertSee('<html lang="en">', false)->assertSee('Steel Wok')
            ->assertSee('<link rel="canonical" href="'.$this->u("/product/{$slug}").'">', false)
            ->assertSee('<link rel="alternate" hreflang="bn" href="'.$this->u("/bn/product/{$slug}").'">', false)
            ->assertSee('<link rel="alternate" hreflang="x-default" href="'.$this->u("/product/{$slug}").'">', false);

        $this->get("/bn/product/{$slug}")->assertOk()->assertSee('<html lang="bn">', false)->assertSee('স্টিলের কড়াই')
            ->assertSee('<title>স্টিলের কড়াই', false)
            ->assertSee('<link rel="canonical" href="'.$this->u("/bn/product/{$slug}").'">', false)
            ->assertSee('<link rel="alternate" hreflang="en" href="'.$this->u("/product/{$slug}").'">', false)
            ->assertSee('href="'.$this->u('/bn/category/'.$category->slug).'"', false)
            ->assertSee('href="'.$this->u('/bn/shop').'"', false)
            ->assertSee('href="'.$this->u('/cart').'"', false);

        $this->get('/bn/shop?sort=price_asc')->assertOk()->assertSee('<link rel="alternate" hreflang="en" href="'.$this->u('/shop?sort=price_asc').'">', false);
    }

    public function test_session_pages_follow_the_language_last_browsed(): void
    {
        $this->enableBangla();

        $this->get('/bn/shop')->assertOk();
        $this->get('/cart')->assertOk()->assertSee('<html lang="bn">', false)->assertDontSee('hreflang', false);
        $this->get('/shop')->assertOk()->assertSee('<html lang="en">', false);
        $this->get('/cart')->assertSee('<html lang="en">', false);
    }

    public function test_private_and_unknown_pages_have_no_bangla_url(): void
    {
        $this->enableBangla();

        foreach (['/bn/cart', '/bn/admin', '/bn/account', '/bn/business/dashboard', '/bn/api/v1/products', '/bn/product', '/bn/nonsense'] as $url) {
            $this->get($url)->assertNotFound();
        }
        $this->get('/bn/business')->assertOk()->assertSee('<html lang="bn">', false);
    }

    public function test_language_switch_goes_to_the_same_page_in_the_other_language(): void
    {
        $this->enableBangla();
        $slug = $this->product->slug;

        $this->from("/product/{$slug}?ref=1")->post('/language', ['locale' => 'bn'])->assertRedirect("/bn/product/{$slug}?ref=1");
        $this->from("/bn/product/{$slug}")->post('/language', ['locale' => 'en'])->assertRedirect("/product/{$slug}");

        $foreign = $this->from('https://evil.example/product/x')->post('/language', ['locale' => 'bn']);
        $this->assertStringNotContainsString('/bn/product/x', (string) $foreign->headers->get('Location'), 'Never redirect based on another site.');

        $this->get("/product/{$slug}")->assertSee('<a href="'.$this->u("/bn/product/{$slug}").'" hreflang="bn"', false);
    }

    public function test_sitemap_lists_both_languages_with_alternates(): void
    {
        $this->enableBangla();
        $this->get('/bn/shop'); /* remembered Bangla must not leak into the sitemap's English URLs */

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('<loc>'.$this->u("/product/{$this->product->slug}").'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.$this->u("/bn/product/{$this->product->slug}").'</loc>', $xml);
        $this->assertStringContainsString('hreflang="x-default" href="'.$this->u('/shop').'"', $xml);
        $this->assertStringNotContainsString('/bn/bn/', $xml);
        $this->assertSame(substr_count($xml, '<url>'), substr_count($xml, 'hreflang="x-default"'), 'Every listed URL carries its alternates.');
    }

    public function test_robots_and_noindex_rules_cover_bangla_urls(): void
    {
        $this->enableBangla();
        config(['seo.indexing_enabled' => true]);

        $this->get('/bn/search?q=wok')->assertSee('noindex, nofollow', false);
        $this->get('/bn/shop')->assertHeaderMissing('X-Robots-Tag');
        $this->get('/robots.txt')->assertSee('Disallow: /bn/search');
    }
}

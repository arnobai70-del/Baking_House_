<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_indexing_is_disabled_by_default(): void
    {
        $product = Product::factory()->withFixedVariant()->create();

        $this->get("/product/{$product->slug}")->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->get('/robots.txt')->assertOk()->assertSee("Disallow: /\n", false)->assertDontSee('Sitemap:');
    }

    public function test_robots_when_indexing_is_enabled(): void
    {
        config(['seo.indexing_enabled' => true]);

        $this->get('/robots.txt')
            ->assertSee('Disallow: /admin')
            ->assertSee('Disallow: /account')
            ->assertSee('Disallow: /search')
            ->assertSee('Sitemap: '.route('sitemap'))
            ->assertDontSee("Disallow: /\n", false);
    }

    public function test_product_page_is_indexable_with_canonical_when_enabled(): void
    {
        config(['seo.indexing_enabled' => true]);
        $product = Product::factory()->withFixedVariant()->create(['seo_title' => 'Custom SEO Title']);

        $this->get("/product/{$product->slug}?utm_source=x")
            ->assertSee('<meta name="robots" content="index, follow">', false)
            ->assertSee('<link rel="canonical" href="'.route('catalog.product', $product->slug).'">', false)
            ->assertSee('<title>Custom SEO Title — '.config('seo.site_name').'</title>', false);
    }

    public function test_filtered_sorted_and_search_pages_are_noindex_with_clean_canonical(): void
    {
        config(['seo.indexing_enabled' => true]);
        $category = Category::factory()->create();

        $this->get('/shop?sort=price_asc')
            ->assertSee('noindex, nofollow', false)
            ->assertSee('<link rel="canonical" href="'.route('catalog.shop').'">', false);
        $this->get("/category/{$category->slug}?in_stock=1")->assertSee('noindex, nofollow', false);
        $this->get('/search?q=wok')->assertSee('noindex, nofollow', false);
        $this->get("/category/{$category->slug}")->assertSee('index, follow', false);
    }

    public function test_paginated_canonical_keeps_page_number(): void
    {
        config(['seo.indexing_enabled' => true]);
        Product::factory()->count(26)->withFixedVariant()->create();

        $this->get('/shop?page=2')->assertSee('<link rel="canonical" href="'.route('catalog.shop').'?page=2">', false);
    }

    public function test_account_and_auth_pages_are_never_indexed(): void
    {
        config(['seo.indexing_enabled' => true]);

        $this->get('/login')->assertSee('noindex, nofollow', false);
        $this->get('/register')->assertSee('noindex, nofollow', false);
    }

    public function test_product_structured_data_uses_real_stored_price_and_availability(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme']);
        $product = Product::factory()->withFixedVariant(245000, 0, ['sku' => 'BH-RST-0001'])->create(['name' => 'GN Pan', 'brand_id' => $brand->id]);

        $schema = $this->productSchema($this->get("/product/{$product->slug}")->getContent());

        $this->assertSame('GN Pan', $schema['name']);
        $this->assertSame('Acme', $schema['brand']['name']);
        $this->assertSame('2450.00', $schema['offers']['price']);
        $this->assertSame('BDT', $schema['offers']['priceCurrency']);
        $this->assertSame('https://schema.org/OutOfStock', $schema['offers']['availability']);
        $this->assertArrayNotHasKey('image', $schema, 'No image markup until an approved real photo exists.');
    }

    public function test_weight_offer_is_per_kg_and_quote_only_has_no_offer(): void
    {
        $weight = Product::factory()->withWeightVariant(65000)->create();
        $quote = Product::factory()->create();
        $quote->variants()->create(['sku' => 'BH-Q-1', 'name' => 'Standard', 'pricing_mode' => 'quote_only', 'selling_unit' => 'piece', 'stock_quantity' => 1, 'is_active' => true]);

        $weightSchema = $this->productSchema($this->get("/product/{$weight->slug}")->getContent());
        $quoteSchema = $this->productSchema($this->get("/product/{$quote->slug}")->getContent());

        $this->assertSame('650.00', $weightSchema['offers']['price']);
        $this->assertSame('KGM', $weightSchema['offers']['unitCode']);
        $this->assertArrayNotHasKey('offers', $quoteSchema);
    }

    public function test_breadcrumb_structured_data(): void
    {
        $parent = Category::factory()->create(['name' => 'Restaurant']);
        $child = Category::factory()->childOf($parent)->create(['name' => 'Woks']);
        $product = Product::factory()->withFixedVariant()->create(['primary_category_id' => $child->id, 'name' => 'Steel Wok']);

        $blocks = $this->jsonLd($this->get("/product/{$product->slug}")->getContent());
        $crumbs = collect($blocks)->firstWhere('@type', 'BreadcrumbList')['itemListElement'];

        $this->assertSame(['Home', 'Restaurant', 'Woks', 'Steel Wok'], array_column($crumbs, 'name'));
    }

    public function test_sitemap_lists_visible_pages_only(): void
    {
        $category = Category::factory()->create();
        $hidden = Category::factory()->inactive()->create();
        $visible = Product::factory()->withFixedVariant()->create(['primary_category_id' => $category->id]);
        $draft = Product::factory()->draft()->withFixedVariant()->create(['primary_category_id' => $category->id]);

        $this->get('/sitemap.xml')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
            ->assertSee(route('catalog.product', $visible->slug), false)
            ->assertSee(route('catalog.category', $category->slug), false)
            ->assertDontSee($draft->slug)
            ->assertDontSee($hidden->slug);

        $this->assertNotFalse(simplexml_load_string($this->get('/sitemap.xml')->getContent()));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function jsonLd(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $matches);

        return array_map(fn (string $json) => json_decode($json, true, flags: JSON_THROW_ON_ERROR), $matches[1]);
    }

    /**
     * @return array<string, mixed>
     */
    private function productSchema(string $html): array
    {
        return collect($this->jsonLd($html))->firstWhere('@type', 'Product');
    }
}

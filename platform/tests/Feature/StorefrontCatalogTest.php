<?php

namespace Tests\Feature;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StorefrontCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_shows_active_categories_and_products_only(): void
    {
        Category::factory()->create(['name' => 'Restaurant Supply']);
        Category::factory()->inactive()->create(['name' => 'Hidden Category']);
        Product::factory()->withFixedVariant()->create(['name' => 'Visible Wok']);
        Product::factory()->draft()->withFixedVariant()->create(['name' => 'Draft Mixer']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Restaurant Supply')
            ->assertDontSee('Hidden Category')
            ->assertSee('Visible Wok')
            ->assertDontSee('Draft Mixer');
    }

    public function test_draft_archived_and_hidden_category_products_return_404(): void
    {
        $draft = Product::factory()->draft()->withFixedVariant()->create();
        $hiddenCategory = Category::factory()->inactive()->create();
        $inHidden = Product::factory()->withFixedVariant()->create(['primary_category_id' => $hiddenCategory->id]);

        $this->get("/product/{$draft->slug}")->assertNotFound();
        $this->get("/product/{$inHidden->slug}")->assertNotFound();
        $this->get("/category/{$hiddenCategory->slug}")->assertNotFound();
    }

    public function test_weight_product_page_exposes_the_rule_for_the_live_calculator(): void
    {
        $product = Product::factory()->withWeightVariant(65000, 18450, ['sku' => 'BH-ING-0001'])->create(['name' => 'Dark Compound Chocolate']);

        $response = $this->get("/product/{$product->slug}")->assertOk()->assertSee('৳650', false)->assertSee('per kg');

        preg_match("/data-product='([^']+)'/", $response->getContent(), $matches);
        $variants = json_decode(html_entity_decode($matches[1]), true);

        $this->assertSame('weight', $variants[0]['pricingMode']);
        $this->assertSame(65000, $variants[0]['weight']['perKgPaisa']);
        $this->assertSame(50, $variants[0]['weight']['stepGrams']);
        $this->assertSame([100, 250, 500, 700, 900, 1000], $variants[0]['weight']['presets']);
    }

    public function test_pack_and_piece_units_are_labelled(): void
    {
        $product = Product::factory()->create(['name' => 'Cake Box']);
        ProductVariant::factory()->for($product)->fixed(95000, 200)->create(['selling_unit' => 'pack', 'pack_size' => 25]);

        $this->get("/product/{$product->slug}")->assertOk()->assertSee('per pack of 25');
    }

    public function test_quote_only_product_shows_price_on_request(): void
    {
        $product = Product::factory()->create(['name' => 'Deck Oven']);
        ProductVariant::factory()->for($product)->quoteOnly()->create();

        $this->get("/product/{$product->slug}")->assertOk()->assertSee('Price on request');
        $this->get('/shop')->assertSee('Request quote');
    }

    public function test_category_listing_includes_subcategory_products(): void
    {
        $parent = Category::factory()->create(['name' => 'Restaurant']);
        $child = Category::factory()->childOf($parent)->create(['name' => 'Woks']);
        Product::factory()->withFixedVariant()->create(['primary_category_id' => $child->id, 'name' => 'Carbon Wok']);
        Product::factory()->withFixedVariant()->create(['name' => 'Unrelated Flour']);

        $this->get("/category/{$parent->slug}")
            ->assertOk()
            ->assertSee('Carbon Wok')
            ->assertDontSee('Unrelated Flour')
            ->assertSee('Woks');
    }

    public function test_search_matches_name_keywords_including_bangla_and_sku(): void
    {
        Product::factory()->withFixedVariant(attributes: ['sku' => 'BH-BKT-0002'])->create(['name' => 'Silicone Mould', 'search_keywords' => 'mold, কেক মোল্ড']);
        Product::factory()->withFixedVariant()->create(['name' => 'Steel Wok']);

        $this->get('/search?q=mold')->assertOk()->assertSee('Silicone Mould')->assertDontSee('Steel Wok');
        $this->get('/search?q='.urlencode('কেক'))->assertSee('Silicone Mould');
        $this->get('/search?q=bkt-0002')->assertSee('Silicone Mould');
        $this->get('/search?q=zzzz')->assertOk()->assertSee('No products match');
    }

    public function test_search_treats_like_wildcards_literally(): void
    {
        Product::factory()->withFixedVariant()->create(['name' => 'Plain Tray']);

        $this->get('/search?q=%25')->assertOk()->assertDontSee('Plain Tray');
    }

    public function test_filters_brand_stock_weight_and_price(): void
    {
        $brand = Brand::factory()->create(['name' => 'Acme', 'slug' => 'acme']);
        Product::factory()->withFixedVariant(250000, 5)->create(['name' => 'Acme Pan', 'brand_id' => $brand->id]);
        Product::factory()->withFixedVariant(10000, 0)->create(['name' => 'Sold Out Bottle']);
        Product::factory()->withWeightVariant(65000)->create(['name' => 'Chocolate By Weight']);

        $this->get('/shop?brand[]=acme')->assertSee('Acme Pan')->assertDontSee('Chocolate By Weight');
        $this->get('/shop?in_stock=1')->assertSee('Acme Pan')->assertDontSee('Sold Out Bottle');
        $this->get('/shop?weight=1')->assertSee('Chocolate By Weight')->assertDontSee('Acme Pan');
        $this->get('/shop?min=1000&max=3000')->assertSee('Acme Pan')->assertDontSee('Sold Out Bottle')->assertDontSee('Chocolate By Weight');
    }

    public function test_sorting_by_price(): void
    {
        Product::factory()->withFixedVariant(300000)->create(['name' => 'Expensive Item']);
        Product::factory()->withFixedVariant(10000)->create(['name' => 'Cheap Item']);

        $this->get('/shop?sort=price_asc')->assertSeeInOrder(['Cheap Item', 'Expensive Item']);
        $this->get('/shop?sort=price_desc')->assertSeeInOrder(['Expensive Item', 'Cheap Item']);
    }

    public function test_listing_paginates(): void
    {
        Product::factory()->count(26)->withFixedVariant()->create();

        $this->get('/shop')->assertOk()->assertSee('rel="next"', false);
        $this->get('/shop?page=2')->assertOk()->assertSee('rel="prev"', false);
    }

    public function test_inactive_variant_hides_its_price_and_stock(): void
    {
        $product = Product::factory()->withFixedVariant(125000, 0, ['is_active' => false])->create();

        $this->assertNull($product->fresh()->price_from_paisa);
        $this->assertFalse($product->fresh()->in_stock);
    }

    public function test_development_banner_shows_outside_production(): void
    {
        $this->get('/')->assertSee('illustrative test data');
    }
}

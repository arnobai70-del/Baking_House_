<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CatalogApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_products_endpoint_lists_visible_products_with_public_prices_only(): void
    {
        $product = Product::factory()->withWeightVariant(65000, 18450, ['sku' => 'BH-ING-0001'])->create(['name' => 'Chocolate']);
        Product::factory()->draft()->withFixedVariant()->create(['name' => 'Secret Draft']);

        $response = $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.name', 'Chocolate')
            ->assertJsonPath('data.0.variants.0.per_kg_paisa', 65000)
            ->assertJsonPath('data.0.variants.0.weight_rules.step_grams', 50)
            ->assertJsonMissingPath('data.0.variants.0.stock_quantity')
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'total']]);
        $this->assertStringNotContainsString('tiers', $response->getContent());
        $this->assertSame($product->url(), $response->json('data.0.url'));
    }

    public function test_product_detail_and_404_for_drafts(): void
    {
        $visible = Product::factory()->withFixedVariant()->create();
        $draft = Product::factory()->draft()->withFixedVariant()->create();

        $this->getJson("/api/v1/products/{$visible->slug}")->assertOk()->assertJsonPath('data.slug', $visible->slug);
        $this->getJson("/api/v1/products/{$draft->slug}")->assertNotFound();
    }

    public function test_categories_endpoint_returns_navigation_tree(): void
    {
        $parent = Category::factory()->create(['name' => 'Bakery', 'sort_order' => 1]);
        Category::factory()->childOf($parent)->create(['name' => 'Moulds']);
        Category::factory()->inactive()->create(['name' => 'Hidden']);

        $this->getJson('/api/v1/categories')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Bakery')
            ->assertJsonPath('data.0.children.0.name', 'Moulds');
    }

    public function test_search_suggestions(): void
    {
        Product::factory()->withFixedVariant()->create(['name' => 'Carbon Steel Wok']);

        $this->getJson('/api/v1/search?q=wok')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Carbon Steel Wok')
            ->assertJsonPath('all_results_url', route('catalog.search', ['q' => 'wok']));
        $this->getJson('/api/v1/search?q=w')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_per_page_is_capped(): void
    {
        $this->getJson('/api/v1/products?per_page=1000')->assertOk()->assertJsonPath('meta.per_page', 60);
    }
}

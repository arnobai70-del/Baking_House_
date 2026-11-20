<?php

namespace Tests\Feature;

use App\Enums\PricingMode;
use App\Models\AuditLog;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 3 gate: admin edits publish correctly without code; duplicate SKU prevented.
 */
class AdminCatalogTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->category = Category::factory()->create(['name' => 'Cake Decoration']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @param  list<array<string, mixed>>|null  $variants
     * @return array<string, mixed>
     */
    private function payload(array $overrides = [], ?array $variants = null): array
    {
        return array_merge([
            'name' => 'Dark Compound Chocolate',
            'status' => 'active',
            'primary_category_id' => $this->category->id,
            'short_description' => 'Compound chocolate for coating.',
            'specifications_text' => "Type: Dark compound\nStorage: Cool, dry place",
            'search_keywords' => 'chocolate, চকলেট',
            'variants' => $variants ?? [[
                'sku' => 'bh-ing-0001',
                'name' => 'Standard',
                'pricing_mode' => 'weight',
                'selling_unit' => 'gram',
                'per_kg' => '650',
                'min_grams' => 100,
                'max_grams' => 25000,
                'step_grams' => 50,
                'preset_grams' => '100, 250, 500, 700',
                'stock_quantity' => 18450,
                'is_active' => 1,
            ]],
        ], $overrides);
    }

    public function test_admin_creates_a_weight_product_that_publishes_immediately(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload())->assertRedirect();

        $product = Product::query()->sole();
        $variant = $product->variants()->sole();

        $this->assertSame('dark-compound-chocolate', $product->slug);
        $this->assertSame(['Type' => 'Dark compound', 'Storage' => 'Cool, dry place'], $product->specifications);
        $this->assertSame('BH-ING-0001', $variant->sku);
        $this->assertSame(PricingMode::Weight, $variant->pricing_mode);
        $this->assertSame(65000, $variant->per_kg_paisa);
        $this->assertNull($variant->price_paisa);
        $this->assertSame([100, 250, 500, 700], $variant->preset_grams);
        $this->assertSame(65000, $product->fresh()->price_from_paisa);
        $this->assertTrue($product->fresh()->in_stock);
        $this->assertTrue($product->categories->contains($this->category));
        $this->assertDatabaseHas('audit_logs', ['action' => 'product.created', 'actor_id' => $this->admin->id]);

        $this->get('/product/dark-compound-chocolate')->assertOk()->assertSee('Dark Compound Chocolate');
    }

    public function test_fixed_pack_product_and_decimal_taka_prices(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload(['name' => 'Cake Box'], [[
            'sku' => 'BH-PKG-0001', 'name' => 'Pack of 25', 'pricing_mode' => 'fixed', 'selling_unit' => 'pack', 'pack_size' => 25,
            'price' => '812.50', 'compare_at' => '1,000', 'stock_quantity' => 200, 'is_active' => 1,
        ]]))->assertRedirect();

        $variant = ProductVariant::query()->sole();
        $this->assertSame(81250, $variant->price_paisa);
        $this->assertSame(100000, $variant->compare_at_paisa);
        $this->assertSame(25, $variant->pack_size);
        $this->assertNull($variant->per_kg_paisa);
    }

    public function test_duplicate_sku_is_rejected_by_validation_and_database(): void
    {
        $other = Product::factory()->withFixedVariant(attributes: ['sku' => 'BH-ING-0001'])->create();

        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload())
            ->assertSessionHasErrors('variants.0.sku');
        $this->assertSame(1, Product::query()->count());

        $this->expectException(UniqueConstraintViolationException::class);
        ProductVariant::factory()->for($other)->create(['sku' => 'BH-ING-0001']);
    }

    public function test_two_variants_in_one_form_cannot_share_a_sku(): void
    {
        $variant = ['name' => 'A', 'pricing_mode' => 'fixed', 'selling_unit' => 'piece', 'price' => '10', 'stock_quantity' => 1, 'is_active' => 1];

        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload([], [
            ['sku' => 'BH-X-1'] + $variant,
            ['sku' => 'bh-x-1'] + $variant,
        ]))->assertSessionHasErrors('variants.0.sku');
    }

    public function test_weight_rules_must_line_up_with_the_step(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload([], [[
            'sku' => 'BH-W-1', 'name' => 'Std', 'pricing_mode' => 'weight', 'selling_unit' => 'gram', 'per_kg' => '650',
            'min_grams' => 120, 'max_grams' => 25000, 'step_grams' => 50, 'preset_grams' => '100, 725', 'stock_quantity' => 0, 'is_active' => 1,
        ]]))->assertSessionHasErrors(['variants.0.step_grams', 'variants.0.preset_grams']);
    }

    public function test_price_change_requires_a_reason_and_is_audited(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload());
        $product = Product::query()->sole();
        $variant = $product->variants()->sole();

        $update = $this->payload([], [[
            'id' => $variant->id, 'sku' => 'BH-ING-0001', 'name' => 'Standard', 'pricing_mode' => 'weight', 'selling_unit' => 'gram',
            'per_kg' => '700', 'min_grams' => 100, 'max_grams' => 25000, 'step_grams' => 50, 'preset_grams' => '100', 'stock_quantity' => 18450, 'is_active' => 1,
        ]]);

        $this->actingAsStaff($this->admin)->put("/admin/products/{$product->id}", $update)->assertSessionHasErrors('price_change_reason');
        $this->assertSame(65000, $variant->fresh()->per_kg_paisa);

        $this->actingAsStaff($this->admin)->put("/admin/products/{$product->id}", $update + ['price_change_reason' => 'Supplier cost increase'])
            ->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(70000, $variant->fresh()->per_kg_paisa);
        $log = AuditLog::query()->where('action', 'product.price_changed')->sole();
        $this->assertSame(['sku' => 'BH-ING-0001', 'per_kg_paisa' => 65000], $log->before);
        $this->assertSame(['sku' => 'BH-ING-0001', 'per_kg_paisa' => 70000], $log->after);
        $this->assertSame('Supplier cost increase', $log->reason);

        $this->get("/product/{$product->slug}")->assertSee('৳700', false);
    }

    public function test_staff_without_price_permission_cannot_change_prices(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload());
        $product = Product::query()->sole();
        $variant = $product->variants()->sole();

        $catalogOnly = Role::create(['name' => 'catalog_editor', 'label' => 'Catalog editor']);
        $catalogOnly->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'catalog.manage'])->pluck('id'));
        $editor = User::factory()->withMfa()->withRole('catalog_editor')->create();

        $this->actingAsStaff($editor)->put("/admin/products/{$product->id}", $this->payload(['name' => 'Renamed Chocolate'], [[
            'id' => $variant->id, 'sku' => 'BH-ING-0001', 'name' => 'Standard', 'pricing_mode' => 'weight', 'selling_unit' => 'gram',
            'per_kg' => '1', 'min_grams' => 100, 'max_grams' => 25000, 'step_grams' => 50, 'stock_quantity' => 18450, 'is_active' => 1,
            'price_change_reason' => 'trying',
        ]]))->assertSessionHasErrors('price_change_reason');

        $this->assertSame(65000, $variant->fresh()->per_kg_paisa);
        $this->assertSame('Dark Compound Chocolate', $product->fresh()->name, 'Whole save is rejected, nothing partially applied.');
    }

    public function test_variant_id_from_another_product_is_rejected(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/products', $this->payload());
        $mine = Product::query()->sole();
        $foreignVariant = ProductVariant::factory()->create(['sku' => 'BH-FOREIGN-1']);

        $this->actingAsStaff($this->admin)->put("/admin/products/{$mine->id}", $this->payload([], [[
            'id' => $foreignVariant->id, 'sku' => 'BH-FOREIGN-1', 'name' => 'Hijack', 'pricing_mode' => 'fixed', 'selling_unit' => 'piece',
            'price' => '1', 'stock_quantity' => 0, 'is_active' => 1,
        ]]))->assertSessionHasErrors('variants.0.id');

        $this->assertSame('Standard', $foreignVariant->fresh()->name);
    }

    public function test_admin_manages_categories_and_brands(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/categories', [
            'name' => 'Chinese Kitchen', 'icon' => 'wok', 'is_active' => 1, 'sort_order' => 2, 'summary' => 'Woks and more',
        ])->assertRedirect();
        $category = Category::query()->where('slug', 'chinese-kitchen')->sole();

        $this->get('/')->assertSee('Chinese Kitchen');

        $this->actingAsStaff($this->admin)->put("/admin/categories/{$category->id}", [
            'name' => 'Chinese Kitchen', 'slug' => 'chinese-kitchen', 'icon' => 'wok', 'is_active' => 0,
        ])->assertRedirect();
        $this->get('/category/chinese-kitchen')->assertNotFound();

        $this->actingAsStaff($this->admin)->post('/admin/brands', ['name' => 'Acme Steel', 'is_active' => 1])->assertRedirect();
        $this->assertDatabaseHas('brands', ['slug' => 'acme-steel']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'category.updated']);
    }

    public function test_category_cannot_be_nested_more_than_two_levels(): void
    {
        $child = Category::factory()->childOf($this->category)->create();

        $this->actingAsStaff($this->admin)->post('/admin/categories', [
            'name' => 'Too Deep', 'icon' => 'box', 'parent_id' => $child->id, 'is_active' => 1,
        ])->assertSessionHasErrors('parent_id');
    }

    public function test_non_staff_cannot_reach_catalog_admin(): void
    {
        $customer = User::factory()->create();

        $this->actingAs($customer)->get('/admin/products')->assertForbidden();
        $this->actingAs($customer)->post('/admin/products', $this->payload())->assertForbidden();
        $this->assertSame(0, Product::query()->count());
    }

    public function test_admin_screens_render(): void
    {
        $product = Product::factory()->withWeightVariant()->create();

        $this->actingAsStaff($this->admin)->get('/admin/products')->assertOk()->assertSee($product->name);
        $this->actingAsStaff($this->admin)->get("/admin/products/{$product->id}/edit")->assertOk()->assertSee('Reason for any price change');
        $this->actingAsStaff($this->admin)->get('/admin/products/create')->assertOk();
        $this->actingAsStaff($this->admin)->get('/admin/categories')->assertOk();
        $this->actingAsStaff($this->admin)->get('/admin/brands')->assertOk();
    }
}

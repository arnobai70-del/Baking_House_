<?php

namespace Tests\Feature;

use App\Enums\PriceListAudience;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Permission;
use App\Models\PriceList;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Promotion;
use App\Models\Role;
use App\Models\ScheduledPriceChange;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin screens for Phase 4: price lists, scheduling, simulator, inventory, promotions, bundles.
 */
class AdminPricingInventoryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private ProductVariant $chocolate;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create();
        $this->chocolate = Product::factory()->withWeightVariant(65000, 10000, ['sku' => 'BH-ING-0001', 'low_stock_threshold' => 2000])->create()->variants()->sole();
    }

    public function test_admin_creates_a_business_price_list_with_volume_tiers(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/price-lists', [
            'name' => 'Trade 2026', 'audience' => 'business', 'is_active' => 1,
            'items' => [
                ['sku' => 'bh-ing-0001', 'min_quantity' => 1, 'price' => '630'],
                ['sku' => 'BH-ING-0001', 'min_quantity' => 5000, 'price' => '600'],
                ['sku' => '', 'min_quantity' => 1, 'price' => ''],
            ],
        ])->assertRedirect();

        $list = PriceList::query()->sole();
        $this->assertSame(PriceListAudience::Business, $list->audience);
        $this->assertSame([63000, 60000], $list->items()->orderBy('min_quantity')->pluck('per_kg_paisa')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'price_list.created']);
    }

    public function test_price_list_validation_and_reason_on_edit(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/price-lists', [
            'name' => 'Bad', 'audience' => 'retail', 'is_active' => 1,
            'items' => [['sku' => 'NOPE', 'min_quantity' => 1, 'price' => '10'], ['sku' => 'BH-ING-0001', 'min_quantity' => 1, 'price' => 'abc']],
        ])->assertSessionHasErrors(['items.0.sku', 'items.1.price']);

        $this->actingAsStaff($this->admin)->post('/admin/price-lists', ['name' => 'Contract', 'audience' => 'contract', 'is_active' => 1])
            ->assertSessionHasErrors('organization_id');

        $list = PriceList::factory()->withTiers($this->chocolate, [[1, 64000]])->create();
        $this->actingAsStaff($this->admin)->put("/admin/price-lists/{$list->id}", [
            'name' => $list->name, 'audience' => 'retail', 'is_active' => 1, 'items' => [['sku' => 'BH-ING-0001', 'min_quantity' => 1, 'price' => '1']],
        ])->assertSessionHasErrors('reason');
        $this->assertSame(64000, $list->items()->value('per_kg_paisa'));
    }

    public function test_contract_list_only_for_approved_organizations(): void
    {
        $pending = Organization::factory()->business()->create();

        $this->actingAsStaff($this->admin)->post('/admin/price-lists', [
            'name' => 'Contract', 'audience' => 'contract', 'organization_id' => $pending->id, 'is_active' => 1,
        ])->assertSessionHasErrors('organization_id');
    }

    public function test_schedule_and_cancel_a_price_change(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/scheduled-prices', [
            'sku' => 'BH-ING-0001', 'field' => 'per_kg_paisa', 'new_price' => '700',
            'effective_at' => now()->addDay()->timezone('Asia/Dhaka')->format('Y-m-d\TH:i'), 'reason' => 'Supplier increase',
        ])->assertSessionHasNoErrors();

        $change = ScheduledPriceChange::query()->sole();
        $this->assertSame(70000, $change->new_value_paisa);
        $this->assertTrue($change->effective_at->isFuture());

        $this->actingAsStaff($this->admin)->post('/admin/scheduled-prices', [
            'sku' => 'BH-ING-0001', 'field' => 'price_paisa', 'new_price' => '1', 'effective_at' => now()->subDay()->format('Y-m-d\TH:i'), 'reason' => 'Wrong field',
        ])->assertSessionHasErrors(['field', 'effective_at']);

        $this->actingAsStaff($this->admin)->delete("/admin/scheduled-prices/{$change->id}")->assertRedirect();
        $this->assertSame('cancelled', $change->fresh()->status);
    }

    public function test_price_simulator_shows_the_engine_result(): void
    {
        $this->actingAsStaff($this->admin)->get('/admin/price-simulator?sku=BH-ING-0001&quantity=700&audience=retail')
            ->assertOk()->assertSee('৳455', false)->assertSee('standard');
    }

    public function test_inventory_movement_reconcile_and_verify_screens(): void
    {
        $this->actingAsStaff($this->admin)->get('/admin/inventory')->assertOk()->assertSee('BH-ING-0001');

        $this->actingAsStaff($this->admin)->post("/admin/inventory/{$this->chocolate->id}/movements", [
            'type' => 'receipt', 'direction' => 'in', 'quantity' => 5000, 'reason' => 'PO-1001',
        ])->assertSessionHasNoErrors();
        $this->assertSame(15000, $this->chocolate->fresh()->stock_quantity);

        $this->actingAsStaff($this->admin)->post("/admin/inventory/{$this->chocolate->id}/movements", [
            'type' => 'damage', 'direction' => 'out', 'quantity' => 999999, 'reason' => 'Too much',
        ])->assertSessionHasErrors('quantity');

        $this->actingAsStaff($this->admin)->post("/admin/inventory/{$this->chocolate->id}/reconcile", [
            'counted_quantity' => 14800, 'reason' => 'Monthly count',
        ])->assertSessionHasNoErrors();
        $this->assertSame(14800, $this->chocolate->fresh()->stock_quantity);

        $this->actingAsStaff($this->admin)->get("/admin/inventory/{$this->chocolate->id}")->assertOk()->assertSee('Stock count correction');
        $this->actingAsStaff($this->admin)->get('/admin/inventory/verify')->assertOk()->assertSee('Every variant reconciles');
    }

    public function test_regular_admin_cannot_make_promotions_stackable_but_super_admin_can(): void
    {
        $payload = ['name' => 'Ten off', 'code' => 'ten', 'discount_type' => 'percentage', 'discount_value' => '10', 'scope' => 'order', 'audiences' => ['retail'], 'is_active' => 1, 'stackable' => 1];

        $this->actingAsStaff($this->admin)->post('/admin/promotions', $payload)->assertRedirect();
        $promotion = Promotion::query()->sole();
        $this->assertSame('TEN', $promotion->code);
        $this->assertSame(1000, $promotion->discount_value);
        $this->assertFalse($promotion->stackable, 'Admin cannot authorise stacking.');

        $owner = User::factory()->superAdmin()->create();
        $this->actingAsStaff($owner)->put("/admin/promotions/{$promotion->id}", $payload)->assertRedirect();
        $this->assertTrue($promotion->fresh()->stackable);
        $this->assertSame(2, AuditLog::query()->whereIn('action', ['promotion.created', 'promotion.updated'])->count());
    }

    public function test_promotion_and_bundle_validation(): void
    {
        $this->actingAsStaff($this->admin)->post('/admin/promotions', [
            'name' => 'Bad', 'discount_type' => 'percentage', 'discount_value' => '150', 'scope' => 'products', 'product_skus' => 'NOPE', 'audiences' => ['retail'],
        ])->assertSessionHasErrors('discount_value');

        $this->actingAsStaff($this->admin)->post('/admin/bundles', [
            'name' => 'Starter', 'discount_type' => 'percentage', 'discount_value' => '10', 'audiences' => ['retail'], 'items' => [['sku' => 'NOPE', 'quantity' => 1]],
        ])->assertSessionHasErrors('items.0.sku');

        $this->actingAsStaff($this->admin)->post('/admin/bundles', [
            'name' => 'Starter Kit', 'discount_type' => 'percentage', 'discount_value' => '10', 'audiences' => ['retail'], 'is_active' => 1,
            'items' => [['sku' => 'BH-ING-0001', 'quantity' => 1000]],
        ])->assertRedirect();

        $this->get('/combo/starter-kit')->assertOk()->assertSee('৳585', false);
        $this->get('/combos')->assertOk()->assertSee('Starter Kit');
    }

    public function test_permissions_separate_pricing_inventory_and_settings(): void
    {
        $role = Role::create(['name' => 'catalog_only', 'label' => 'Catalog only']);
        $role->permissions()->sync(Permission::query()->whereIn('name', ['admin.access', 'catalog.manage'])->pluck('id'));
        $editor = User::factory()->withMfa()->withRole('catalog_only')->create();

        foreach (['/admin/price-lists', '/admin/scheduled-prices', '/admin/price-simulator', '/admin/promotions', '/admin/bundles', '/admin/inventory', '/admin/settings/storefront'] as $url) {
            $this->actingAsStaff($editor)->get($url)->assertForbidden();
        }
        $this->actingAsStaff($editor)->get('/admin/products')->assertOk();

        $customer = User::factory()->create();
        $this->actingAs($customer)->get('/admin/inventory')->assertForbidden();
    }

    public function test_admin_screens_render(): void
    {
        $list = PriceList::factory()->withTiers($this->chocolate, [[1, 60000]])->create();
        $promotion = Promotion::factory()->coupon('X1')->create();

        foreach (['/admin/price-lists', '/admin/price-lists/create', "/admin/price-lists/{$list->id}/edit", '/admin/scheduled-prices', '/admin/price-simulator',
            '/admin/promotions', '/admin/promotions/create', "/admin/promotions/{$promotion->id}/edit", '/admin/bundles', '/admin/bundles/create',
            '/admin/inventory', '/admin/inventory?low=1', '/admin/settings/storefront', '/admin/products/create'] as $url) {
            $this->actingAsStaff($this->admin)->get($url)->assertOk();
        }
    }
}

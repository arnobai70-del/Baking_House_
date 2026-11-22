<?php

namespace Tests\Feature;

use App\Enums\StaffPermission;
use App\Models\Permission;
use App\Models\ProductImage;
use App\Models\ProductVariant;
use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 9.5 (D4): live work queues on the admin dashboard, each gated by the permission of its screen.
 */
class AdminDashboardCountsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_super_admin_sees_all_four_counts_from_real_records(): void
    {
        $low = ProductVariant::factory()->create(['stock_quantity' => 3]);
        $low->forceFill(['low_stock_threshold' => 5])->save();
        ProductVariant::factory()->create(['stock_quantity' => 50]);
        ProductImage::factory()->create();
        ProductImage::factory()->approved()->create();

        $page = $this->actingAsStaff(User::factory()->superAdmin()->create())->get('/admin')->assertOk();

        $page->assertSeeInOrder(['Orders needing action', '0'])
            ->assertSeeInOrder(['Low-stock SKUs', '1'])
            ->assertSeeInOrder(['Photos awaiting approval', '1'])
            ->assertSeeInOrder(['Open quotation requests', '0'])
            ->assertSee(route('admin.inventory.index', ['low' => 1]), false)
            ->assertDontSee('৳');
    }

    public function test_staff_only_see_counts_for_screens_they_can_open(): void
    {
        $role = Role::query()->create(['name' => 'photo_reviewer', 'label' => 'Photo reviewer']);
        $role->permissions()->attach(Permission::query()->whereIn('name', [StaffPermission::AccessAdmin->value, StaffPermission::ApproveMedia->value])->pluck('id'));
        $reviewer = User::factory()->withMfa()->create();
        $reviewer->roles()->attach($role);

        $this->actingAsStaff($reviewer)->get('/admin')->assertOk()
            ->assertSee('Photos awaiting approval')
            ->assertDontSee('Orders needing action')
            ->assertDontSee('Low-stock SKUs')
            ->assertDontSee('Open quotation requests');
    }
}

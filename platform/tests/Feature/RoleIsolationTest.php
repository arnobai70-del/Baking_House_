<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Phase 2 gate: "secure role isolation demonstrated". Blueprint §07 + negative tests for org ID swapping.
 */
class RoleIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_retail_customer_cannot_reach_business_reseller_or_admin_areas(): void
    {
        $retail = User::factory()->create();

        $this->actingAs($retail)->get('/account')->assertOk();
        $this->actingAs($retail)->get('/business/dashboard')->assertForbidden();
        $this->actingAs($retail)->get('/reseller/dashboard')->assertForbidden();
        $this->actingAs($retail)->get('/admin')->assertForbidden();
    }

    public function test_business_member_reaches_only_the_business_portal(): void
    {
        $owner = User::factory()->create();
        $organization = Organization::factory()->business()->approved()->withMember($owner)->create(['name' => 'Spice Garden']);

        $this->actingAs($owner)->get('/business/dashboard')->assertOk()->assertSee('Spice Garden');
        $this->actingAs($owner)->get('/reseller/dashboard')->assertForbidden();
        $this->actingAs($owner)->get('/admin')->assertForbidden();
    }

    public function test_reseller_does_not_automatically_get_business_access(): void
    {
        $reseller = User::factory()->create();
        Organization::factory()->reseller()->approved()->withMember($reseller)->create();

        $this->actingAs($reseller)->get('/reseller/dashboard')->assertOk();
        $this->actingAs($reseller)->get('/business/dashboard')->assertForbidden();
    }

    public function test_pending_and_suspended_organizations_grant_no_access(): void
    {
        $applicant = User::factory()->create();
        Organization::factory()->business()->withMember($applicant)->create();
        $suspended = User::factory()->create();
        Organization::factory()->business()->suspended()->withMember($suspended)->create();

        $this->actingAs($applicant)->get('/business/dashboard')->assertForbidden();
        $this->actingAs($suspended)->get('/business/dashboard')->assertForbidden();
    }

    public function test_cannot_switch_context_into_another_organization(): void
    {
        $alice = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($alice)->create();
        $foreign = Organization::factory()->business()->approved()->create();

        $this->actingAs($alice)
            ->post('/account/context', ['organization_id' => $foreign->id])
            ->assertForbidden();
    }

    public function test_tampered_session_organization_id_is_ignored(): void
    {
        $alice = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($alice)->create(['name' => 'Alice Bakery']);
        Organization::factory()->business()->approved()->create(['name' => 'Rival Restaurant']);
        $foreignId = Organization::query()->where('name', 'Rival Restaurant')->value('id');

        $this->actingAs($alice)
            ->withSession([OrganizationContext::SESSION_KEY => $foreignId])
            ->get('/business/dashboard')
            ->assertOk()
            ->assertSee('Alice Bakery')
            ->assertDontSee('Rival Restaurant');
    }

    public function test_member_of_two_businesses_must_choose_explicitly(): void
    {
        $user = User::factory()->create();
        $first = Organization::factory()->business()->approved()->withMember($user)->create(['name' => 'First Café']);
        Organization::factory()->business()->approved()->withMember($user)->create(['name' => 'Second Café']);

        $this->actingAs($user)->get('/business/dashboard')->assertForbidden();

        $this->actingAs($user)->post('/account/context', ['organization_id' => $first->id])->assertRedirect();
        $this->actingAs($user)->get('/business/dashboard')->assertOk()->assertSee('First Café');
    }

    public function test_admin_can_open_admin_but_cannot_change_roles(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin)->get('/admin')->assertOk();
        $this->actingAs($admin)->get('/admin/audit')->assertOk();
        $this->actingAs($admin)
            ->put("/admin/users/{$target->id}/roles", ['roles' => ['admin'], 'reason' => 'Trying to escalate'])
            ->assertForbidden();

        $this->assertFalse($target->fresh()->isStaff());
    }

    public function test_super_admin_can_assign_roles_with_an_audited_reason(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($owner)
            ->put("/admin/users/{$target->id}/roles", ['roles' => ['admin'], 'reason' => 'New operations staff'])
            ->assertRedirect();

        $this->assertTrue($target->fresh()->hasRole('admin'));
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'user.roles_changed',
            'actor_id' => $owner->id,
            'subject_id' => $target->id,
            'reason' => 'New operations staff',
        ]);
    }

    public function test_nobody_can_change_their_own_roles(): void
    {
        $owner = User::factory()->superAdmin()->create();

        $this->actingAs($owner)
            ->put("/admin/users/{$owner->id}/roles", ['roles' => [], 'reason' => 'Self change attempt'])
            ->assertForbidden();
    }

    public function test_role_change_requires_a_reason(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $target = User::factory()->create();

        $this->actingAs($owner)
            ->put("/admin/users/{$target->id}/roles", ['roles' => ['admin']])
            ->assertSessionHasErrors('reason');
    }

    public function test_deactivated_staff_lose_permissions(): void
    {
        $admin = User::factory()->admin()->create(['is_active' => false]);

        $this->assertFalse($admin->can('admin.access'));
    }
}

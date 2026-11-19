<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApprovalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_customer_applies_for_a_business_account_which_starts_pending(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/apply', [
            'type' => 'business',
            'name' => 'Dhaka Wok House',
            'business_category' => 'Chinese restaurant',
            'contact_phone' => '01812345678',
        ])->assertRedirect(route('account'));

        $organization = Organization::query()->sole();
        $this->assertSame(OrganizationStatus::Pending, $organization->status);
        $this->assertSame('owner', $user->roleIn($organization)?->value);
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.applied', 'actor_id' => $user->id]);

        $this->actingAs($user)->get('/business/dashboard')->assertForbidden();
    }

    public function test_applicant_cannot_set_their_own_status(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post('/apply', [
            'type' => 'reseller',
            'name' => 'Sneaky Shop',
            'contact_phone' => '01812345678',
            'status' => 'approved',
        ]);

        $this->assertSame(OrganizationStatus::Pending, Organization::query()->sole()->status);
    }

    public function test_admin_approves_with_reason_and_member_gains_access(): void
    {
        $admin = User::factory()->admin()->create();
        $owner = User::factory()->create();
        $organization = Organization::factory()->business()->withMember($owner)->create();

        $this->actingAs($admin)
            ->patch("/admin/organizations/{$organization->id}/status", ['status' => 'approved', 'reason' => 'Trade licence checked'])
            ->assertRedirect();

        $organization->refresh();
        $this->assertTrue($organization->isApproved());
        $this->assertSame($admin->id, $organization->approved_by);

        $log = AuditLog::query()->where('action', 'organization.status_changed')->sole();
        $this->assertSame(['status' => 'pending'], $log->before);
        $this->assertSame(['status' => 'approved'], $log->after);
        $this->assertSame('Trade licence checked', $log->reason);

        $this->actingAs($owner)->get('/business/dashboard')->assertOk();
    }

    public function test_decision_requires_a_reason(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->create();

        $this->actingAs($admin)
            ->patch("/admin/organizations/{$organization->id}/status", ['status' => 'approved'])
            ->assertSessionHasErrors('reason');
        $this->assertFalse($organization->fresh()->isApproved());
    }

    public function test_staff_cannot_approve_an_organization_they_belong_to(): void
    {
        $admin = User::factory()->admin()->create();
        $organization = Organization::factory()->withMember($admin)->create();

        $this->actingAs($admin)
            ->patch("/admin/organizations/{$organization->id}/status", ['status' => 'approved', 'reason' => 'Approving myself'])
            ->assertForbidden();
    }

    public function test_customers_cannot_approve_organizations(): void
    {
        $customer = User::factory()->create();
        $organization = Organization::factory()->withMember($customer)->create();

        $this->actingAs($customer)
            ->patch("/admin/organizations/{$organization->id}/status", ['status' => 'approved', 'reason' => 'Please approve'])
            ->assertForbidden();
    }

    public function test_admin_dashboard_lists_pending_applications(): void
    {
        $admin = User::factory()->admin()->create();
        Organization::factory()->create(['name' => 'Pending Patisserie']);
        Organization::factory()->approved()->create(['name' => 'Already Approved']);

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Pending Patisserie')->assertDontSee('Already Approved');
    }
}

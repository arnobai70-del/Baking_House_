<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\AuditLog;
use App\Models\NotificationLog;
use App\Models\OrganizationInvitation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\PortalHelpers;
use Tests\TestCase;

/**
 * Team seats: invitations bound to an email, one owner minimum, owner-only management.
 */
class TeamTest extends TestCase
{
    use PortalHelpers, RefreshDatabase;

    public function test_owner_invites_and_the_invited_email_accepts_once(): void
    {
        [$organization, $owner] = $this->businessWith();
        $chef = User::factory()->create(['email' => 'chef@example.com']);

        $response = $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'Chef@Example.com', 'role' => 'buyer'])->assertSessionHasNoErrors();
        $link = session('invitation_link');
        $token = basename((string) $link);
        $invitation = OrganizationInvitation::query()->sole();

        $this->assertSame('chef@example.com', $invitation->email);
        $this->assertSame(hash('sha256', $token), $invitation->token_hash, 'Only the hash is stored.');
        $this->assertSame('c***@example.com', NotificationLog::query()->where('template', 'team_invitation')->sole()->recipient_masked);
        $response->assertRedirect();

        $this->actingAs($chef)->get("/invitations/{$token}")->assertOk()->assertSee($organization->name);
        $this->actingAs($chef)->post("/invitations/{$token}")->assertRedirect(route('business.dashboard'));
        $this->assertSame(OrganizationRole::Buyer, $chef->roleIn($organization));
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.member_joined']);

        $this->actingAs($chef)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');
    }

    public function test_a_forwarded_link_is_useless_for_another_account(): void
    {
        [$organization, $owner] = $this->businessWith();
        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'chef@example.com', 'role' => 'owner']);
        $token = basename((string) session('invitation_link'));
        $intruder = User::factory()->create(['email' => 'someone-else@example.com']);

        $this->actingAs($intruder)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');
        $this->assertFalse($organization->hasMember($intruder));
        $this->actingAs($intruder)->get('/invitations/'.str_repeat('x', 40))->assertNotFound();
    }

    public function test_expired_and_revoked_invitations_cannot_be_used(): void
    {
        [$organization, $owner] = $this->businessWith();
        $chef = User::factory()->create(['email' => 'chef@example.com']);
        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'chef@example.com', 'role' => 'buyer']);
        $token = basename((string) session('invitation_link'));

        $this->travel(8)->days();
        $this->actingAs($chef)->post("/invitations/{$token}")->assertSessionHasErrors('invitation');
        $this->travelBack();

        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'chef@example.com', 'role' => 'buyer']);
        $second = basename((string) session('invitation_link'));
        $this->actingAs($owner)->delete(route('business.team.revoke', OrganizationInvitation::query()->latest('id')->first()));
        $this->actingAs($chef)->post("/invitations/{$second}")->assertSessionHasErrors('invitation');
        $this->assertFalse($organization->hasMember($chef));
    }

    public function test_only_owners_manage_the_team_and_the_last_owner_stays(): void
    {
        [$organization, $owner] = $this->businessWith();
        $buyer = $this->addMember($organization, OrganizationRole::Buyer);

        $this->actingAs($buyer)->post('/business/team/invitations', ['email' => 'x@example.com', 'role' => 'owner'])->assertForbidden();
        $this->actingAs($buyer)->patch(route('business.team.role', $buyer), ['role' => 'owner'])->assertForbidden();
        $this->actingAs($buyer)->delete(route('business.team.remove', $owner))->assertForbidden();

        $this->actingAs($owner)->patch(route('business.team.role', $owner), ['role' => 'viewer'])->assertSessionHasErrors('member');
        $this->actingAs($owner)->delete(route('business.team.remove', $owner))->assertSessionHasErrors('member');
        $this->assertSame(OrganizationRole::Owner, $owner->roleIn($organization));

        $this->actingAs($owner)->patch(route('business.team.role', $buyer), ['role' => 'owner'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->patch(route('business.team.role', $owner), ['role' => 'viewer'])->assertSessionHasNoErrors();
        $this->assertSame(OrganizationRole::Viewer, $owner->fresh()->roleIn($organization));
        $this->assertSame(2, AuditLog::query()->where('action', 'organization.member_role_changed')->count());
    }

    public function test_removed_members_lose_access_and_existing_members_cannot_be_reinvited(): void
    {
        [$organization, $owner] = $this->businessWith();
        $buyer = $this->addMember($organization, OrganizationRole::Buyer, ['email' => 'buyer@example.com']);

        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'BUYER@example.com', 'role' => 'buyer'])->assertSessionHasErrors('email');

        $this->actingAs($owner)->delete(route('business.team.remove', $buyer))->assertSessionHasNoErrors();
        $this->assertFalse($organization->hasMember($buyer));
        $this->actingAs($buyer)->get('/business/dashboard')->assertForbidden();
        $this->assertDatabaseHas('audit_logs', ['action' => 'organization.member_removed']);
    }

    public function test_team_size_is_capped(): void
    {
        config(['commerce.organization_max_members' => 2]);
        [, $owner] = $this->businessWith();

        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'one@example.com', 'role' => 'buyer'])->assertSessionHasNoErrors();
        $this->actingAs($owner)->post('/business/team/invitations', ['email' => 'two@example.com', 'role' => 'buyer'])->assertSessionHasErrors('email');
    }

    public function test_only_owners_see_member_emails_and_invitation_controls(): void
    {
        [$organization, $owner] = $this->businessWith();
        $viewer = $this->addMember($organization, OrganizationRole::Viewer);

        $this->actingAs($viewer)->get('/business/team')->assertOk()
            ->assertSee($owner->name)->assertDontSee($owner->email)->assertDontSee('Invite a member');
        $this->actingAs($owner)->get('/business/team')->assertOk()
            ->assertSee($viewer->email)->assertSee('Invite a member');
    }
}

<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Services\ApiTokenIssuer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

/**
 * Sanctum API: scoped, expiring, revocable tokens with role separation (owner approval §2).
 */
class ApiAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    /**
     * @param  list<string>  $abilities
     */
    private function issueToken(User $user, array $abilities = []): string
    {
        return $this->postJson('/api/v1/auth/tokens', [
            'identifier' => $user->phone,
            'password' => 'password',
            'device_name' => 'test-device',
            'abilities' => $abilities,
            'mfa_code' => $user->isStaff() ? $this->totp() : null,
        ])->assertCreated()->json('token');
    }

    private function bearer(string $token): static
    {
        $this->app['auth']->forgetGuards();

        return $this->withHeader('Authorization', "Bearer {$token}");
    }

    public function test_customer_gets_a_scoped_expiring_token_by_phone(): void
    {
        $user = User::factory()->create(['phone' => '01712345678']);

        $response = $this->postJson('/api/v1/auth/tokens', [
            'identifier' => '+8801712345678',
            'password' => 'password',
            'device_name' => 'android',
        ])->assertCreated();

        $this->assertSame(['account:read', 'orders:read', 'orders:write'], $response->json('abilities'));
        $this->assertNotNull($response->json('expires_at'));
        $this->bearer($response->json('token'))->getJson('/api/v1/me')->assertOk()->assertJsonPath('data.id', $user->id);
    }

    public function test_bad_credentials_and_inactive_users_get_no_token(): void
    {
        User::factory()->create(['phone' => '01712345678']);
        User::factory()->inactive()->create(['phone' => '01812345678']);

        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01712345678', 'password' => 'wrong', 'device_name' => 'x'])
            ->assertUnprocessable()->assertJsonPath('errors.identifier.0', 'These credentials do not match our records.');
        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01812345678', 'password' => 'password', 'device_name' => 'x'])
            ->assertUnprocessable();
        $this->assertSame(0, PersonalAccessToken::query()->count());
    }

    public function test_requested_abilities_are_clamped_to_what_the_user_may_hold(): void
    {
        $retail = User::factory()->create();

        $token = $this->issueToken($retail, ['account:read', 'admin:read', 'business:read']);

        $this->assertSame(['account:read'], PersonalAccessToken::findToken($token)->abilities);
        $this->bearer($token)->getJson('/api/v1/admin/organizations')->assertForbidden();
        $this->bearer($token)->getJson('/api/v1/business/organization')->assertForbidden();
    }

    public function test_token_without_the_needed_ability_is_refused(): void
    {
        $owner = User::factory()->create();
        Organization::factory()->business()->approved()->withMember($owner)->create();

        $token = $this->issueToken($owner, ['account:read']);

        $this->bearer($token)->getJson('/api/v1/business/organization')->assertForbidden();
    }

    public function test_business_token_reaches_only_its_own_organization(): void
    {
        $owner = User::factory()->create();
        $mine = Organization::factory()->business()->approved()->withMember($owner)->create(['name' => 'My Kitchen']);
        $foreign = Organization::factory()->business()->approved()->create();
        $token = $this->issueToken($owner);

        $this->bearer($token)->getJson('/api/v1/business/organization')->assertOk()->assertJsonPath('data.name', 'My Kitchen');
        $this->bearer($token)->withHeader('X-Organization-Id', (string) $mine->id)->getJson('/api/v1/business/organization')->assertOk();
        $this->bearer($token)->withHeader('X-Organization-Id', (string) $foreign->id)->getJson('/api/v1/business/organization')->assertForbidden();
        $this->bearer($token)->withHeader('X-Organization-Id', 'abc')->getJson('/api/v1/business/organization')->assertForbidden();
        $this->bearer($token)->getJson('/api/v1/reseller/organization')->assertForbidden();
    }

    public function test_reseller_token_cannot_reach_business_or_admin(): void
    {
        $reseller = User::factory()->create();
        Organization::factory()->reseller()->approved()->withMember($reseller)->create();
        $token = $this->issueToken($reseller);

        $this->assertContains('reseller:read', PersonalAccessToken::findToken($token)->abilities);
        $this->bearer($token)->getJson('/api/v1/reseller/organization')->assertOk();
        $this->bearer($token)->getJson('/api/v1/business/organization')->assertForbidden();
        $this->bearer($token)->getJson('/api/v1/admin/organizations')->assertForbidden();
    }

    public function test_admin_token_is_short_lived_and_still_needs_permissions(): void
    {
        $admin = User::factory()->admin()->create();
        Organization::factory()->create(['name' => 'Pending One']);

        $response = $this->postJson('/api/v1/auth/tokens', ['identifier' => $admin->phone, 'password' => 'password', 'device_name' => 'ops', 'mfa_code' => $this->totp()]);
        $expiresAt = now()->parse($response->json('expires_at'));

        $this->assertTrue($expiresAt->lessThanOrEqualTo(now()->addMinutes(ApiTokenIssuer::STAFF_TTL_MINUTES)->addMinute()));
        $this->bearer($response->json('token'))->getJson('/api/v1/admin/organizations?status=pending')
            ->assertOk()->assertJsonPath('data.0.name', 'Pending One');
    }

    public function test_admin_permission_is_rechecked_when_staff_role_is_removed(): void
    {
        $admin = User::factory()->admin()->create();
        $token = $this->issueToken($admin);

        $admin->roles()->detach();

        $this->bearer($token)->getJson('/api/v1/admin/organizations')->assertForbidden();
    }

    public function test_expired_tokens_are_rejected(): void
    {
        $user = User::factory()->create();
        $token = $this->issueToken($user);

        $this->travel(ApiTokenIssuer::CUSTOMER_TTL_MINUTES + 1)->minutes();

        $this->bearer($token)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_tokens_can_be_listed_and_revoked_but_only_your_own(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();
        $aliceToken = $this->issueToken($alice);
        $bobToken = $this->issueToken($bob);
        $bobTokenId = PersonalAccessToken::findToken($bobToken)->id;

        $this->bearer($aliceToken)->getJson('/api/v1/auth/tokens')->assertOk()->assertJsonCount(1, 'data')
            ->assertJsonMissingPath('data.0.token');
        $this->bearer($aliceToken)->deleteJson("/api/v1/auth/tokens/{$bobTokenId}")->assertNotFound();
        $this->bearer($bobToken)->getJson('/api/v1/me')->assertOk();

        $this->bearer($aliceToken)->deleteJson('/api/v1/auth/tokens/current')->assertNoContent();
        $this->bearer($aliceToken)->getJson('/api/v1/me')->assertUnauthorized();
    }

    public function test_deactivated_user_token_stops_working_and_is_revoked(): void
    {
        $user = User::factory()->create();
        $token = $this->issueToken($user);

        $user->forceFill(['is_active' => false])->save();

        $this->bearer($token)->getJson('/api/v1/me')->assertUnauthorized();
        $this->assertNull(PersonalAccessToken::findToken($token));
    }

    public function test_unauthenticated_requests_get_json_401(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized()->assertJsonStructure(['message']);
    }

    public function test_token_issuing_is_throttled(): void
    {
        User::factory()->create(['phone' => '01712345678']);

        foreach (range(1, 5) as $attempt) {
            $this->postJson('/api/v1/auth/tokens', ['identifier' => '01712345678', 'password' => 'wrong', 'device_name' => 'x']);
        }

        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01712345678', 'password' => 'password', 'device_name' => 'x'])
            ->assertTooManyRequests();
    }
}

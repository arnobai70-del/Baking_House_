<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\MfaRecoveryCode;
use App\Models\Role;
use App\Models\User;
use App\Services\Mfa\MfaManager;
use App\Services\Mfa\MfaSession;
use Database\Factories\UserFactory;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\PersonalAccessToken;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

/**
 * Mandatory staff TOTP MFA on web and API (owner security requirements, 2026-11-20).
 */
class StaffMfaTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->admin = User::factory()->admin()->create(['email' => 'admin@example.com', 'phone' => '01711111111']);
    }

    private function passwordStep(string $identifier = 'admin@example.com'): void
    {
        $this->post('/login', ['identifier' => $identifier, 'password' => 'password'])->assertRedirect();
    }

    /* ---------------- Web login ---------------- */

    public function test_password_alone_never_logs_staff_in(): void
    {
        $this->passwordStep();

        $this->assertGuest();
        $this->get('/admin')->assertRedirect(route('login'));
    }

    public function test_valid_totp_completes_staff_login(): void
    {
        $this->passwordStep();

        $this->post('/mfa/challenge', ['code' => $this->totp()])->assertRedirect(route('admin.dashboard'));

        $this->assertAuthenticatedAs($this->admin);
        $this->get('/admin')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['action' => 'auth.staff_login', 'subject_id' => $this->admin->id]);
    }

    public function test_wrong_code_is_rejected_and_audited_without_the_code(): void
    {
        $this->passwordStep();

        $this->post('/mfa/challenge', ['code' => '987123'])->assertSessionHasErrors('code');

        $this->assertGuest();
        $log = AuditLog::query()->where('action', 'mfa.failed')->sole();
        $this->assertSame(['method' => 'totp', 'context' => 'web_login'], $log->after);
        $this->assertStringNotContainsString('987123', json_encode($log->toArray()));
    }

    public function test_a_code_cannot_be_replayed(): void
    {
        $code = $this->totp();
        $this->passwordStep();
        $this->post('/mfa/challenge', ['code' => $code]);
        $this->post('/logout');

        $this->passwordStep();
        $this->post('/mfa/challenge', ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_challenge_without_a_password_step_goes_to_login(): void
    {
        $this->get('/mfa/challenge')->assertRedirect(route('login'));
        $this->post('/mfa/challenge', ['code' => $this->totp()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_pending_sign_in_expires(): void
    {
        $this->passwordStep();

        $this->travel(MfaSession::PENDING_TTL_SECONDS + 5)->seconds();

        $this->post('/mfa/challenge', ['code' => $this->totp()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_mfa_attempts_are_rate_limited(): void
    {
        $this->passwordStep();

        foreach (range(1, 5) as $attempt) {
            $this->post('/mfa/challenge', ['code' => '000000']);
        }

        $this->post('/mfa/challenge', ['code' => $this->totp()])->assertTooManyRequests();
        $this->assertGuest();
    }

    /* ---------------- Enrollment flag is not authentication ---------------- */

    public function test_enrolled_staff_session_without_verification_is_forced_through_mfa(): void
    {
        $this->actingAs($this->admin)->get('/admin')->assertRedirect(route('mfa.challenge'));

        $this->assertGuest();
    }

    public function test_user_promoted_to_staff_mid_session_must_pass_mfa(): void
    {
        $customer = User::factory()->withMfa()->create();
        $this->actingAs($customer)->get('/account')->assertOk();

        $customer->roles()->attach(Role::query()->where('name', 'admin')->value('id'));

        $this->actingAs($customer->fresh())->get('/account')->assertRedirect(route('mfa.challenge'));
    }

    public function test_customers_are_not_asked_for_mfa(): void
    {
        User::factory()->create(['email' => 'cook@example.com']);

        $this->post('/login', ['identifier' => 'cook@example.com', 'password' => 'password'])->assertRedirect(route('account'));
        $this->assertAuthenticated();
    }

    /* ---------------- Enrollment ---------------- */

    public function test_unenrolled_staff_must_enroll_and_secret_is_encrypted_at_rest(): void
    {
        $staff = User::factory()->withRole('admin')->create(['email' => 'new@example.com']);

        $this->passwordStep('new@example.com');
        $this->get('/mfa/setup')->assertOk()->assertSee('<svg', false)->assertHeader('Cache-Control', 'no-store, private');

        $staff->refresh();
        $raw = DB::table('users')->where('id', $staff->id)->value('mfa_secret');
        $this->assertNotNull($staff->mfa_secret);
        $this->assertStringNotContainsString($staff->mfa_secret, $raw, 'Secret must be encrypted in the database.');
        $this->assertFalse($staff->hasMfaEnrolled());
        $this->assertGuest();

        $google2fa = new Google2FA;
        $response = $this->post('/mfa/setup', ['code' => $google2fa->getCurrentOtp($staff->mfa_secret)])->assertOk();

        $this->assertTrue($staff->fresh()->hasMfaEnrolled());
        $this->assertAuthenticatedAs($staff);
        $this->assertSame(MfaManager::RECOVERY_CODE_COUNT, MfaRecoveryCode::query()->where('user_id', $staff->id)->count());

        preg_match_all('/[A-Z0-9]{5}-[A-Z0-9]{5}/', $response->getContent(), $codes);
        $this->assertCount(MfaManager::RECOVERY_CODE_COUNT, array_unique($codes[0]));
        foreach ($codes[0] as $code) {
            $this->assertDatabaseMissing('mfa_recovery_codes', ['code_hash' => $code]);
        }
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.enrolled', 'subject_id' => $staff->id]);
    }

    public function test_wrong_enrollment_code_does_not_enroll(): void
    {
        $staff = User::factory()->withRole('admin')->create(['email' => 'new@example.com']);
        $this->passwordStep('new@example.com');
        $this->get('/mfa/setup');

        $this->post('/mfa/setup', ['code' => '123456'])->assertSessionHasErrors('code');

        $this->assertFalse($staff->fresh()->hasMfaEnrolled());
        $this->assertGuest();
    }

    /* ---------------- Recovery codes ---------------- */

    public function test_recovery_codes_are_single_use(): void
    {
        $codes = app(MfaManager::class)->regenerateRecoveryCodes($this->admin);

        $this->passwordStep();
        $this->post('/mfa/challenge', ['recovery_code' => strtolower($codes[0])])->assertRedirect(route('account.security'));
        $this->assertAuthenticatedAs($this->admin);
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.recovery_used']);
        $this->post('/logout');

        $this->passwordStep();
        $this->post('/mfa/challenge', ['recovery_code' => $codes[0]])->assertSessionHasErrors('recovery_code');
        $this->assertGuest();
    }

    public function test_regenerating_recovery_codes_requires_reauthentication(): void
    {
        $old = app(MfaManager::class)->regenerateRecoveryCodes($this->admin);

        $this->actingAsStaff($this->admin)->post('/account/security/recovery-codes', ['current_password' => 'wrong', 'code' => $this->totp()])
            ->assertSessionHasErrors('code');

        $this->actingAsStaff($this->admin)->post('/account/security/recovery-codes', ['current_password' => 'password', 'code' => $this->totp()])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $this->assertFalse(app(MfaManager::class)->useRecoveryCode($this->admin, $old[0]), 'Old codes stop working.');
    }

    /* ---------------- Reset ---------------- */

    public function test_self_reset_requires_password_and_code_then_revokes_tokens(): void
    {
        $this->admin->createToken('ops', ['admin:read']);

        $this->actingAsStaff($this->admin)->post('/account/security/reset-mfa', ['current_password' => 'password', 'code' => '000000'])
            ->assertSessionHasErrors('code');
        $this->assertTrue($this->admin->fresh()->hasMfaEnrolled());

        $this->actingAsStaff($this->admin)->post('/account/security/reset-mfa', ['current_password' => 'password', 'code' => $this->totp()])
            ->assertRedirect(route('mfa.setup'));

        $this->assertFalse($this->admin->fresh()->hasMfaEnrolled());
        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertGuest();
        $log = AuditLog::query()->where('action', 'mfa.reset')->sole();
        $this->assertSame(1, $log->after['api_tokens_revoked']);
    }

    public function test_reset_invalidates_other_verified_sessions(): void
    {
        $verifiedEarlier = time() - 120;
        app(MfaManager::class)->reset($this->admin);
        $this->admin->forceFill(['mfa_secret' => UserFactory::TEST_MFA_SECRET, 'mfa_confirmed_at' => now()])->save();

        $this->actingAs($this->admin)->withSession(['mfa' => ['verified_at' => $verifiedEarlier]])
            ->get('/admin')->assertRedirect(route('mfa.challenge'));
    }

    public function test_super_admin_can_reset_another_staff_member_with_reauthentication(): void
    {
        $owner = User::factory()->superAdmin()->create();
        $this->admin->createToken('ops', ['admin:read']);

        $this->actingAsStaff($owner)->post("/admin/users/{$this->admin->id}/mfa-reset", [
            'current_password' => 'password', 'code' => $this->totp(), 'reason' => 'Lost phone, identity confirmed by call',
        ])->assertRedirect();

        $this->assertFalse($this->admin->fresh()->hasMfaEnrolled());
        $this->assertSame(0, $this->admin->tokens()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'mfa.reset', 'subject_id' => $this->admin->id, 'reason' => 'Lost phone, identity confirmed by call']);
    }

    public function test_regular_admin_cannot_reset_someone_elses_mfa(): void
    {
        $other = User::factory()->admin()->create();

        $this->actingAsStaff($this->admin)->post("/admin/users/{$other->id}/mfa-reset", [
            'current_password' => 'password', 'code' => $this->totp(), 'reason' => 'Not allowed',
        ])->assertForbidden();

        $this->assertTrue($other->fresh()->hasMfaEnrolled());
    }

    /* ---------------- API ---------------- */

    public function test_unenrolled_staff_cannot_get_api_tokens(): void
    {
        User::factory()->withRole('admin')->create(['phone' => '01722222222']);

        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01722222222', 'password' => 'password', 'device_name' => 'x'])
            ->assertForbidden();

        $this->assertSame(0, PersonalAccessToken::query()->count());
        $this->assertDatabaseHas('audit_logs', ['action' => 'api.token_denied']);
    }

    public function test_enrolled_staff_need_a_fresh_code_for_each_token(): void
    {
        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01711111111', 'password' => 'password', 'device_name' => 'x'])
            ->assertUnprocessable()->assertJsonValidationErrors('mfa_code');
        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01711111111', 'password' => 'password', 'device_name' => 'x', 'mfa_code' => '000000'])
            ->assertUnprocessable();

        $code = $this->totp();
        $response = $this->postJson('/api/v1/auth/tokens', ['identifier' => '01711111111', 'password' => 'password', 'device_name' => 'ops', 'mfa_code' => $code])
            ->assertCreated();

        $this->postJson('/api/v1/auth/tokens', ['identifier' => '01711111111', 'password' => 'password', 'device_name' => 'ops2', 'mfa_code' => $code])
            ->assertUnprocessable();

        $issued = AuditLog::query()->where('action', 'api.token_issued')->sole();
        $this->assertSame('verified', $issued->after['mfa']);
        $this->assertStringNotContainsString($response->json('token'), json_encode(AuditLog::all()->toArray()));
        $this->assertStringNotContainsString(explode('|', $response->json('token'))[1], json_encode(AuditLog::all()->toArray()));
    }

    public function test_audit_log_never_contains_secrets_or_codes(): void
    {
        $codes = app(MfaManager::class)->regenerateRecoveryCodes($this->admin);
        $this->passwordStep();
        $this->post('/mfa/challenge', ['code' => '111111']);
        $this->post('/mfa/challenge', ['recovery_code' => $codes[1]]);

        $all = json_encode(AuditLog::all()->toArray());
        $this->assertStringNotContainsString(UserFactory::TEST_MFA_SECRET, $all);
        $this->assertStringNotContainsString('111111', $all);
        foreach ($codes as $code) {
            $this->assertStringNotContainsString($code, $all);
        }
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_register_with_a_bangladesh_mobile_number(): void
    {
        $response = $this->post('/register', [
            'name' => 'Rahim Uddin',
            'phone' => '+880 1712-345678',
            'password' => 'secret-pass',
            'password_confirmation' => 'secret-pass',
        ]);

        $response->assertRedirect(route('account'));
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['phone' => '01712345678', 'email' => null]);
    }

    public function test_registration_rejects_invalid_and_duplicate_phones(): void
    {
        User::factory()->create(['phone' => '01712345678']);

        $this->post('/register', ['name' => 'A', 'phone' => '12345', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertSessionHasErrors('phone');
        $this->post('/register', ['name' => 'B', 'phone' => '01712345678', 'password' => 'secret-pass', 'password_confirmation' => 'secret-pass'])
            ->assertSessionHasErrors('phone');
        $this->assertGuest();
    }

    public function test_user_can_log_in_with_phone_or_email(): void
    {
        User::factory()->create(['phone' => '01712345678', 'email' => 'cook@example.com']);

        $this->post('/login', ['identifier' => '+8801712345678', 'password' => 'password'])->assertRedirect(route('account'));
        $this->assertAuthenticated();

        $this->post('/logout');
        $this->assertGuest();

        $this->post('/login', ['identifier' => 'COOK@example.com', 'password' => 'password'])->assertRedirect(route('account'));
        $this->assertAuthenticated();
    }

    public function test_staff_are_sent_to_mfa_and_not_logged_in_by_password_alone(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        User::factory()->admin()->create(['email' => 'staff@example.com']);

        $this->post('/login', ['identifier' => 'staff@example.com', 'password' => 'password'])
            ->assertRedirect(route('mfa.challenge'));
        $this->assertGuest();
    }

    public function test_wrong_password_and_unknown_user_get_the_same_error(): void
    {
        User::factory()->create(['phone' => '01712345678']);

        $wrong = $this->post('/login', ['identifier' => '01712345678', 'password' => 'nope']);
        $unknown = $this->post('/login', ['identifier' => '01799999999', 'password' => 'nope']);

        $wrong->assertSessionHasErrors(['identifier' => 'These credentials do not match our records.']);
        $unknown->assertSessionHasErrors(['identifier' => 'These credentials do not match our records.']);
        $this->assertGuest();
    }

    public function test_deactivated_user_cannot_log_in(): void
    {
        User::factory()->inactive()->create(['phone' => '01712345678']);

        $this->post('/login', ['identifier' => '01712345678', 'password' => 'password'])->assertSessionHasErrors('identifier');
        $this->assertGuest();
    }

    public function test_deactivated_user_is_signed_out_mid_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/account')->assertOk();

        $user->forceFill(['is_active' => false])->save();

        $this->actingAs($user)->get('/account')->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_login_is_throttled_after_five_attempts(): void
    {
        User::factory()->create(['phone' => '01712345678']);

        foreach (range(1, 5) as $attempt) {
            $this->post('/login', ['identifier' => '01712345678', 'password' => 'wrong']);
        }

        $this->post('/login', ['identifier' => '01712345678', 'password' => 'password'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_guests_are_redirected_from_the_account_page(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }
}

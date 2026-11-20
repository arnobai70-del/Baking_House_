<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => '01'.fake()->unique()->numerify('7########'),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'is_active' => true,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Attach a seeded staff role (run RolesAndPermissionsSeeder first).
     */
    public function withRole(string $roleName): static
    {
        return $this->afterCreating(function (User $user) use ($roleName): void {
            $user->roles()->attach(Role::query()->where('name', $roleName)->firstOrFail());
        });
    }

    /**
     * Known TOTP secret for tests only (base32). Never used outside the test suite.
     */
    public const TEST_MFA_SECRET = 'JBSWY3DPEHPK3PXPJBSWY3DPEHPK3PXP';

    /**
     * Confirmed TOTP enrollment (encrypted secret via the model cast).
     */
    public function withMfa(): static
    {
        return $this->state(fn (array $attributes) => [
            'mfa_secret' => self::TEST_MFA_SECRET,
            'mfa_confirmed_at' => now()->subMinute(),
        ]);
    }

    /**
     * Staff states enroll MFA by default; use withRole() for an unenrolled staff member.
     */
    public function admin(): static
    {
        return $this->withMfa()->withRole(Role::Admin);
    }

    public function superAdmin(): static
    {
        return $this->withMfa()->withRole(Role::SuperAdmin);
    }
}

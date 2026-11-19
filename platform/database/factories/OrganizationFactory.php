<?php

namespace Database\Factories;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => OrganizationType::Business,
            'name' => fake()->company(),
            'business_category' => fake()->randomElement(['Restaurant', 'Café', 'Bakery', 'Chinese restaurant']),
            'contact_phone' => '01'.fake()->numerify('8########'),
            'status' => OrganizationStatus::Pending,
        ];
    }

    public function business(): static
    {
        return $this->state(fn (array $attributes) => ['type' => OrganizationType::Business]);
    }

    public function reseller(): static
    {
        return $this->state(fn (array $attributes) => ['type' => OrganizationType::Reseller, 'business_category' => 'Retail shop']);
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrganizationStatus::Approved, 'approved_at' => now()]);
    }

    public function suspended(): static
    {
        return $this->state(fn (array $attributes) => ['status' => OrganizationStatus::Suspended]);
    }

    public function withMember(User $user, OrganizationRole $role = OrganizationRole::Owner): static
    {
        return $this->afterCreating(function (Organization $organization) use ($user, $role): void {
            $organization->members()->attach($user, ['role' => $role->value]);
        });
    }
}

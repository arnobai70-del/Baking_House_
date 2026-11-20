<?php

namespace Tests\Support;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Quotation;
use App\Models\User;
use App\Services\Quotations\QuotationService;
use Carbon\CarbonImmutable;

/**
 * Shared setup for Phase 6 portal tests (all data illustrative).
 */
trait PortalHelpers
{
    /**
     * @return array{0: Organization, 1: User}
     */
    protected function businessWith(OrganizationRole $role = OrganizationRole::Owner, string $name = 'Test Restaurant'): array
    {
        $user = User::factory()->create(['phone' => '017'.fake()->unique()->numerify('########')]);
        $organization = Organization::factory()->business()->approved()->withMember($user, $role)->create(['name' => $name]);

        return [$organization, $user];
    }

    /**
     * @return array{0: Organization, 1: User}
     */
    protected function resellerWith(OrganizationRole $role = OrganizationRole::Owner, string $name = 'Test Reseller'): array
    {
        $user = User::factory()->create(['phone' => '018'.fake()->unique()->numerify('########')]);
        $organization = Organization::factory()->reseller()->approved()->withMember($user, $role)->create(['name' => $name]);

        return [$organization, $user];
    }

    protected function addMember(Organization $organization, OrganizationRole $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $organization->members()->attach($user->id, ['role' => $role->value]);

        return $user;
    }

    /**
     * A requested quotation for the organization, then issued by staff with the given lines.
     *
     * @param  list<array{sku?: ?string, description?: ?string, quantity: string, price: string}>  $lines
     */
    protected function issuedQuotation(Organization $organization, User $requester, array $lines, ?User $staff = null, int $validDays = 14): Quotation
    {
        $service = app(QuotationService::class);
        $quotation = $service->request([
            'contact_name' => $requester->name, 'contact_phone' => '01712345678', 'contact_email' => $requester->email,
            'request_text' => 'Test request', 'customer_note' => null,
        ], $requester, $organization);

        $staff ??= User::factory()->admin()->create();

        return $service->issue($quotation, $service->parseLines($lines), CarbonImmutable::now()->addDays($validDays), 'Test terms', 'Internal', $staff);
    }
}

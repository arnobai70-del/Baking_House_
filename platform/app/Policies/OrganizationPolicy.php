<?php

namespace App\Policies;

use App\Enums\OrganizationStatus;
use App\Enums\StaffPermission;
use App\Models\Organization;
use App\Models\User;

/**
 * Ownership checks for organizations. Menu visibility is never the gate (docs/roles-permissions.md).
 */
class OrganizationPolicy
{
    /**
     * Members see their own organization; staff with customer access see any.
     */
    public function view(User $user, Organization $organization): bool
    {
        return $organization->hasMember($user) || $user->hasPermission(StaffPermission::ManageCustomers);
    }

    /**
     * Approve, reject or suspend. Staff only, and never your own organization.
     */
    public function decide(User $user, Organization $organization): bool
    {
        return $user->hasPermission(StaffPermission::ApproveOrganizations)
            && ! $organization->hasMember($user)
            && $organization->status !== OrganizationStatus::Rejected;
    }
}

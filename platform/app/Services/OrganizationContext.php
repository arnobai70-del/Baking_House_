<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Resolves which approved organization a user is acting for.
 *
 * The chosen organization id lives in the session, but it is ALWAYS re-validated against the
 * user's approved memberships — a tampered or stale id is ignored, never trusted.
 */
class OrganizationContext
{
    public const SESSION_KEY = 'current_organization_id';

    public function __construct(private Session $session) {}

    public function resolve(User $user, OrganizationType $type): ?Organization
    {
        $organizations = $user->approvedOrganizations($type);

        $selectedId = $this->session->get(self::SESSION_KEY);
        $selected = $organizations->firstWhere('id', $selectedId);

        if ($selected !== null) {
            return $selected;
        }

        return $organizations->count() === 1 ? $organizations->first() : null;
    }

    /**
     * Switch context. Returns false when the user is not an approved member.
     */
    public function switchTo(User $user, int $organizationId): bool
    {
        $isMember = collect(OrganizationType::cases())
            ->flatMap(fn (OrganizationType $type) => $user->approvedOrganizations($type))
            ->contains('id', $organizationId);

        if (! $isMember) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, $organizationId);

        return true;
    }
}

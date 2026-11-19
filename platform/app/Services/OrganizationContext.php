<?php

namespace App\Services;

use App\Enums\OrganizationType;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Contracts\Session\Session;

/**
 * Resolves which approved organization a user is acting for.
 *
 * Web: the chosen id lives in the session and is re-validated on every request; a tampered or
 * stale id is ignored. API: the caller names the organization in the X-Organization-Id header;
 * an id the user is not an approved member of is rejected outright (strict mode).
 */
class OrganizationContext
{
    public const SESSION_KEY = 'current_organization_id';

    public function __construct(private ?Session $session = null) {}

    /**
     * @param  bool  $strict  when true, a preferred id that is not a valid membership resolves to null
     */
    public function resolve(User $user, OrganizationType $type, ?int $preferredId = null, bool $strict = false): ?Organization
    {
        $organizations = $user->approvedOrganizations($type);

        if ($preferredId !== null) {
            $selected = $organizations->firstWhere('id', $preferredId);

            if ($selected !== null || $strict) {
                return $selected;
            }
        }

        return $organizations->count() === 1 ? $organizations->first() : null;
    }

    public function resolveFromSession(User $user, OrganizationType $type): ?Organization
    {
        $selectedId = $this->session?->get(self::SESSION_KEY);

        return $this->resolve($user, $type, is_numeric($selectedId) ? (int) $selectedId : null);
    }

    /**
     * Switch web context. Returns false when the user is not an approved member.
     */
    public function switchTo(User $user, int $organizationId): bool
    {
        $isMember = collect(OrganizationType::cases())
            ->flatMap(fn (OrganizationType $type) => $user->approvedOrganizations($type))
            ->contains('id', $organizationId);

        if (! $isMember || $this->session === null) {
            return false;
        }

        $this->session->put(self::SESSION_KEY, $organizationId);

        return true;
    }
}

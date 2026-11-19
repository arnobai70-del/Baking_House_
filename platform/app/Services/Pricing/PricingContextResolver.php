<?php

namespace App\Services\Pricing;

use App\Enums\ApiAbility;
use App\Enums\OrganizationType;
use App\Http\Middleware\EnsureOrganizationAccess;
use App\Models\Organization;
use App\Models\User;
use App\Services\OrganizationContext;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Derives the PricingContext for a request.
 *
 * - Guests and plain customers: retail.
 * - Approved business / reseller members: the organization chosen in the session (web) or named in
 *   X-Organization-Id (API, strictly validated, token must hold the matching read ability); with exactly
 *   one approved membership it is used automatically. Ambiguous or invalid → retail, never someone else's.
 */
class PricingContextResolver
{
    public function forWeb(Request $request): PricingContext
    {
        $user = $request->user();
        if ($user === null) {
            return PricingContext::retail();
        }

        $selectedId = $request->hasSession() ? $request->session()->get(OrganizationContext::SESSION_KEY) : null;

        return $this->choose($user, $this->memberships($user), is_numeric($selectedId) ? (int) $selectedId : null, strict: false);
    }

    public function forApi(Request $request): PricingContext
    {
        $user = $request->user('sanctum');
        if ($user === null || ! $user->is_active) {
            return PricingContext::retail();
        }

        $token = $user->currentAccessToken();
        $memberships = $this->memberships($user)->filter(function (Organization $organization) use ($token): bool {
            $ability = $organization->type === OrganizationType::Business ? ApiAbility::BusinessRead : ApiAbility::ResellerRead;

            return $token === null || $token->can($ability->value);
        });

        $header = $request->header(EnsureOrganizationAccess::HEADER);
        $requestedId = $header === null ? null : filter_var($header, FILTER_VALIDATE_INT);
        if ($requestedId === false) {
            abort(403, 'Invalid organization.');
        }

        return $this->choose($user, $memberships, $requestedId, strict: $requestedId !== null);
    }

    /**
     * @param  Collection<int, Organization>  $memberships
     */
    private function choose(User $user, Collection $memberships, ?int $requestedId, bool $strict): PricingContext
    {
        if ($requestedId !== null) {
            $selected = $memberships->firstWhere('id', $requestedId);
            if ($selected !== null) {
                return PricingContext::forOrganization($selected, $user->id);
            }
            abort_if($strict, 403, 'You are not an approved member of that organization.');
        }

        return $memberships->count() === 1
            ? PricingContext::forOrganization($memberships->first(), $user->id)
            : PricingContext::retail($user->id);
    }

    /**
     * @return Collection<int, Organization>
     */
    private function memberships(User $user): Collection
    {
        return collect(OrganizationType::cases())
            ->flatMap(fn (OrganizationType $type) => $user->approvedOrganizations($type))
            ->values();
    }
}

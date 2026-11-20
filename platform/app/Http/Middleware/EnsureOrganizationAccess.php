<?php

namespace App\Http\Middleware;

use App\Enums\OrganizationType;
use App\Services\OrganizationContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate for the business and reseller portals (web and API): the user must be an approved
 * member of an organization of the given type. The resolved organization is attached to the
 * request so controllers never read an organization id from user input.
 *
 * Usage: ->middleware('organization:business') or 'organization:reseller'
 */
class EnsureOrganizationAccess
{
    public const REQUEST_ATTRIBUTE = 'organization';

    public const ROLE_ATTRIBUTE = 'organization_role';

    public const HEADER = 'X-Organization-Id';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $type): Response
    {
        $organizationType = OrganizationType::from($type);
        $context = new OrganizationContext($request->hasSession() ? $request->session() : null);

        if ($request->hasHeader(self::HEADER)) {
            $requestedId = filter_var($request->header(self::HEADER), FILTER_VALIDATE_INT);
            $organization = $requestedId === false
                ? null
                : $context->resolve($request->user(), $organizationType, $requestedId, strict: true);
        } else {
            $organization = $context->resolveFromSession($request->user(), $organizationType);
        }

        if ($organization === null) {
            abort(403, "This area is only for approved {$organizationType->label()} members.");
        }

        /* Web: opening a portal makes that organization the account you are buying for, so cart
           and checkout prices always match the portal you are working in. */
        if (! $request->hasHeader(self::HEADER) && $request->hasSession()
            && (int) $request->session()->get(OrganizationContext::SESSION_KEY) !== $organization->id) {
            $context->switchTo($request->user(), $organization->id);
        }

        $request->attributes->set(self::REQUEST_ATTRIBUTE, $organization);
        $request->attributes->set(self::ROLE_ATTRIBUTE, $request->user()->roleIn($organization));

        return $next($request);
    }
}

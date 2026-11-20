<?php

namespace App\Http\Controllers\Portal;

use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureOrganizationAccess;
use App\Models\Order;
use App\Models\Organization;
use App\Services\Pricing\PricingContext;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Shared plumbing for the business (/business/*) and reseller (/reseller/*) portals.
 *
 * The organization and the member's role always come from EnsureOrganizationAccess (an approved
 * membership re-checked on every request), never from a route or form parameter. Records that
 * are addressed by URL (orders, quotations, addresses, members) are looked up and then checked
 * against that organization; a mismatch is a 404 so other organizations' ids are not confirmed.
 */
abstract class PortalController extends Controller
{
    protected function organization(Request $request): Organization
    {
        return $request->attributes->get(EnsureOrganizationAccess::REQUEST_ATTRIBUTE);
    }

    protected function role(Request $request): OrganizationRole
    {
        return $request->attributes->get(EnsureOrganizationAccess::ROLE_ATTRIBUTE) ?? OrganizationRole::Viewer;
    }

    protected function portal(Request $request): string
    {
        return $this->organization($request)->type->value;
    }

    protected function context(Request $request): PricingContext
    {
        return PricingContext::forOrganization($this->organization($request), $request->user()->id);
    }

    protected function requireBuyer(Request $request): void
    {
        abort_unless($this->role($request)->canPlaceOrders(), 403, __('Your role is view-only.'));
    }

    protected function requireOwner(Request $request): void
    {
        abort_unless($this->role($request)->canManageTeam(), 403, __('Only the account owner can do this.'));
    }

    protected function ownOrder(Request $request, Order $order): Order
    {
        abort_unless($order->organization_id === $this->organization($request)->id, 404);

        return $order;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function page(Request $request, string $view, string $section, array $data = []): View
    {
        return view($view, $data + [
            'organization' => $this->organization($request),
            'role' => $this->role($request),
            'portal' => $this->portal($request),
            'section' => $section,
        ]);
    }
}

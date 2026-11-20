<?php

namespace App\Http\Controllers\Storefront;

use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Support\Seo;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Public B2B landing pages (/business, /reseller): what the account offers, how to apply, and
 * (business) a quotation request form. No prices are shown here.
 */
class PortalLandingController extends Controller
{
    public function __invoke(Request $request, string $type): View
    {
        $organizationType = OrganizationType::from($type);
        $user = $request->user();

        return view('storefront.portal-landing', [
            'seo' => new Seo(
                title: $organizationType === OrganizationType::Business ? __('Business accounts for restaurants, cafés and bakeries') : __('Reseller accounts'),
                description: $organizationType === OrganizationType::Business
                    ? __('Verified business accounts: protected bulk pricing, quotations, team ordering and invoices.')
                    : __('Approved reseller accounts: assigned rate card, bulk reorders and monthly statements.'),
                canonical: route($type.'.landing'),
            ),
            'type' => $organizationType,
            'membership' => $user?->approvedOrganizations($organizationType)->first(),
            'prefill' => ['name' => $user?->name, 'phone' => $user?->phone, 'email' => $user?->email],
        ]);
    }
}

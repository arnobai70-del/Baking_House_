<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureOrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Reseller dashboard (/reseller/dashboard). Reseller and business contexts are separate.
 */
class ResellerPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organization = $request->attributes->get(EnsureOrganizationAccess::REQUEST_ATTRIBUTE);

        return view('portal.dashboard', [
            'organization' => $organization,
            'memberRole' => $request->user()->roleIn($organization),
            'title' => 'Reseller dashboard',
        ]);
    }
}

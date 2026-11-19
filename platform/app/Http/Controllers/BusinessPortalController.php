<?php

namespace App\Http\Controllers;

use App\Http\Middleware\EnsureOrganizationAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Business dashboard (/business/dashboard). The organization comes from the verified
 * request context set by EnsureOrganizationAccess — never from a route or form parameter.
 */
class BusinessPortalController extends Controller
{
    public function __invoke(Request $request): View
    {
        $organization = $request->attributes->get(EnsureOrganizationAccess::REQUEST_ATTRIBUTE);

        return view('portal.dashboard', [
            'organization' => $organization,
            'memberRole' => $request->user()->roleIn($organization),
            'title' => 'Business dashboard',
        ]);
    }
}

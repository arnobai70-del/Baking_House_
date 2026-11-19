<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\EnsureOrganizationAccess;
use App\Http\Resources\OrganizationResource;
use Illuminate\Http\Request;

/**
 * GET /api/v1/business/organization and /api/v1/reseller/organization.
 * The organization is resolved and verified by EnsureOrganizationAccess.
 */
class OrganizationPortalController extends Controller
{
    public function __invoke(Request $request): OrganizationResource
    {
        return new OrganizationResource($request->attributes->get(EnsureOrganizationAccess::REQUEST_ATTRIBUTE));
    }
}

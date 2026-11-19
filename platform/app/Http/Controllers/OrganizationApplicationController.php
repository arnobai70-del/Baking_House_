<?php

namespace App\Http\Controllers;

use App\Enums\OrganizationRole;
use App\Enums\OrganizationType;
use App\Http\Requests\OrganizationApplicationRequest;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Apply for a business or reseller account. Creates a Pending organization owned by the applicant.
 */
class OrganizationApplicationController extends Controller
{
    public function create(OrganizationType $type): View
    {
        return view('organizations.apply', ['type' => $type]);
    }

    public function store(OrganizationApplicationRequest $request, AuditLogger $audit): RedirectResponse
    {
        $organization = DB::transaction(function () use ($request, $audit): Organization {
            $organization = Organization::create($request->validated());
            $organization->members()->attach($request->user(), ['role' => OrganizationRole::Owner->value]);

            $audit->record('organization.applied', $organization, after: $organization->only(['type', 'name', 'status']));

            return $organization;
        });

        return redirect()->route('account')->with(
            'status',
            "Application for “{$organization->name}” received. Our team will review it before business pricing is enabled.",
        );
    }
}

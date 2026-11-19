<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\OrganizationDecisionRequest;
use App\Models\Organization;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Approve, reject or suspend a business / reseller organization. Always audited with a reason.
 */
class OrganizationApprovalController extends Controller
{
    public function __invoke(OrganizationDecisionRequest $request, Organization $organization, AuditLogger $audit): RedirectResponse
    {
        $status = OrganizationStatus::from($request->validated('status'));
        $before = ['status' => $organization->status->value];

        DB::transaction(function () use ($organization, $status, $request, $audit, $before): void {
            $organization->status = $status;
            if ($status === OrganizationStatus::Approved) {
                $organization->approved_at = now();
                $organization->approved_by = $request->user()->getKey();
            }
            $organization->save();

            $audit->record(
                'organization.status_changed',
                $organization,
                $before,
                ['status' => $status->value],
                $request->validated('reason'),
            );
        });

        return back()->with('status', "{$organization->name} is now {$status->value}.");
    }
}

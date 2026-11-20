<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Enums\OrganizationType;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff directory of business and reseller organizations: members and roles, contract price
 * lists, recent orders and quotations. Approval decisions stay on the dashboard (separate
 * permission); price lists are edited under Pricing.
 */
class OrganizationController extends Controller
{
    public function index(Request $request): View
    {
        $type = OrganizationType::tryFrom((string) $request->query('type', ''));
        $status = OrganizationStatus::tryFrom((string) $request->query('status', ''));
        $search = trim((string) $request->query('q', ''));

        return view('admin.organizations.index', [
            'organizations' => Organization::query()
                ->when($type, fn ($query) => $query->where('type', $type->value))
                ->when($status, fn ($query) => $query->where('status', $status->value))
                ->when($search !== '', fn ($query) => $query->where('name', 'like', '%'.addcslashes($search, '%_\\').'%'))
                ->withCount(['members', 'orders'])
                ->orderBy('name')
                ->paginate(30)
                ->withQueryString(),
            'type' => $type,
            'status' => $status,
            'search' => $search,
        ]);
    }

    public function show(Organization $organization): View
    {
        return view('admin.organizations.show', [
            'organization' => $organization->load([
                'members:id,name,email,phone',
                'addresses',
                'contractPriceLists' => fn ($query) => $query->withCount('items'),
            ]),
            'orders' => $organization->orders()->limit(10)->get(),
            'quotations' => $organization->quotations()->limit(10)->get(),
            'invitations' => $organization->invitations()->limit(10)->get(),
        ]);
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Enums\OrganizationStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('admin.dashboard', [
            'pendingOrganizations' => Organization::query()
                ->where('status', OrganizationStatus::Pending->value)
                ->with('members:id,name,phone')
                ->oldest()
                ->get(),
        ]);
    }
}

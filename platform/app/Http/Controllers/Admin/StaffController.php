<?php

namespace App\Http\Controllers\Admin;

use App\Enums\StaffPermission;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Staff & roles (roles.manage — super admin): who holds which role, the role → permission matrix,
 * and finding an account to give a staff role. Changes go through the audited
 * UserRoleController (reason required; nobody can change their own roles).
 */
class StaffController extends Controller
{
    public function __invoke(Request $request): View
    {
        $search = trim((string) $request->query('q', ''));

        return view('admin.staff', [
            'staff' => User::query()->whereHas('roles')->with('roles:id,name,label')->orderBy('name')->get(['id', 'name', 'email', 'phone', 'is_active', 'mfa_confirmed_at']),
            'found' => $search === '' ? collect() : User::query()->whereDoesntHave('roles')
                ->where(fn ($match) => $match->where('email', $search)->orWhere('phone', $search))->limit(5)->get(['id', 'name', 'email', 'phone']),
            'search' => $search,
            'roles' => Role::query()->with('permissions:id,name')->orderBy('id')->get(),
            'permissions' => Permission::query()->orderBy('id')->get(['id', 'name', 'label']),
            'superAdminOnly' => array_map(fn (StaffPermission $permission) => $permission->value, StaffPermission::superAdminOnly()),
        ]);
    }
}

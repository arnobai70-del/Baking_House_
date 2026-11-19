<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Super-admin only: assign staff roles. Every change is audited with before/after and reason.
 */
class UserRoleController extends Controller
{
    public function __invoke(UpdateUserRolesRequest $request, User $user, AuditLogger $audit): RedirectResponse
    {
        $before = $user->roles()->pluck('name')->sort()->values()->all();
        $roleIds = Role::query()->whereIn('name', $request->validated('roles'))->pluck('id');

        DB::transaction(function () use ($user, $roleIds, $audit, $before, $request): void {
            $user->roles()->sync($roleIds);
            $user->flushPermissionCache();

            $audit->record(
                'user.roles_changed',
                $user,
                ['roles' => $before],
                ['roles' => $user->roles()->pluck('name')->sort()->values()->all()],
                $request->validated('reason'),
            );
        });

        return back()->with('status', "Roles updated for {$user->name}.");
    }
}

<?php

namespace Database\Seeders;

use App\Enums\StaffPermission;
use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

/**
 * Reference data: staff roles and permissions, derived from App\Enums\StaffPermission.
 * Idempotent — safe to run on every deploy.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $permissionIds = collect(StaffPermission::cases())->mapWithKeys(function (StaffPermission $permission): array {
            $model = Permission::query()->updateOrCreate(['name' => $permission->value], ['label' => $permission->label()]);

            return [$permission->value => $model->id];
        });

        $notInAdmin = array_map(fn (StaffPermission $permission) => $permission->value, StaffPermission::notInAdminRole());

        Role::query()->updateOrCreate(['name' => Role::SuperAdmin], ['label' => 'Super admin'])
            ->permissions()->sync($permissionIds->values());

        /* Admin: everything except super-admin-only permissions and financial reports. */
        Role::query()->updateOrCreate(['name' => Role::Admin], ['label' => 'Admin'])
            ->permissions()->sync($permissionIds->except($notInAdmin)->values());

        /* Finance: financial reporting only, assigned explicitly by the super admin (add it next to "admin"). */
        Role::query()->updateOrCreate(['name' => 'finance'], ['label' => 'Finance (financial reports)'])
            ->permissions()->sync($permissionIds->only([
                StaffPermission::AccessAdmin->value, StaffPermission::ViewReports->value, StaffPermission::ViewFinancialReports->value,
            ])->values());
    }
}

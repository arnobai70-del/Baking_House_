<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * LOCAL DEVELOPMENT ONLY — one demo login per role. Never runs outside the local environment.
 * All demo accounts use the password "password".
 */
class LocalDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local')) {
            $this->command?->warn('LocalDemoSeeder skipped: not a local environment.');

            return;
        }

        User::factory()->withRole(Role::SuperAdmin)->create(['name' => 'Demo Super Admin', 'email' => 'superadmin@example.test', 'phone' => '01700000001']);
        User::factory()->withRole(Role::Admin)->create(['name' => 'Demo Admin', 'email' => 'admin@example.test', 'phone' => '01700000002']);
        User::factory()->create(['name' => 'Demo Retail Customer', 'email' => 'retail@example.test', 'phone' => '01700000003']);

        $businessOwner = User::factory()->create(['name' => 'Demo Restaurant Owner', 'email' => 'business@example.test', 'phone' => '01700000004']);
        Organization::factory()->business()->approved()->withMember($businessOwner)->create(['name' => 'Demo Restaurant Ltd.']);

        $reseller = User::factory()->create(['name' => 'Demo Reseller', 'email' => 'reseller@example.test', 'phone' => '01700000005']);
        Organization::factory()->reseller()->approved()->withMember($reseller)->create(['name' => 'Demo Supplies']);

        $applicant = User::factory()->create(['name' => 'Demo Applicant', 'email' => 'applicant@example.test', 'phone' => '01700000006']);
        Organization::factory()->business()->withMember($applicant)->create(['name' => 'Pending Café']);
    }
}

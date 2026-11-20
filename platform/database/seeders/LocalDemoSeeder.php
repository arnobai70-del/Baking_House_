<?php

namespace Database\Seeders;

use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\Role;
use App\Models\User;
use App\Services\Quotations\QuotationService;
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
        $restaurant = Organization::factory()->business()->approved()->withMember($businessOwner)->create(['name' => 'Demo Restaurant Ltd.']);
        $viewer = User::factory()->create(['name' => 'Demo Accounts (view only)', 'email' => 'accounts@example.test', 'phone' => '01700000007']);
        $restaurant->members()->attach($viewer->id, ['role' => OrganizationRole::Viewer->value]);
        $restaurant->addresses()->forceCreate([
            'label' => 'Main kitchen (demo)', 'recipient_name' => 'Demo Restaurant Owner', 'phone' => '01700000004',
            'line' => 'House 1, Road 1 (demo)', 'area' => 'Gulshan', 'district' => 'Dhaka', 'is_default' => true,
        ]);
        app(QuotationService::class)->request([
            'contact_name' => 'Demo Restaurant Owner', 'contact_phone' => '01700000004', 'contact_email' => 'business@example.test',
            'request_text' => "Deck oven 2-deck x 1\nTakeaway boxes x 10 cartons (demo request)", 'customer_note' => null,
        ], $businessOwner, $restaurant);

        $reseller = User::factory()->create(['name' => 'Demo Reseller', 'email' => 'reseller@example.test', 'phone' => '01700000005']);
        Organization::factory()->reseller()->approved()->withMember($reseller)->create(['name' => 'Demo Supplies']);

        $applicant = User::factory()->create(['name' => 'Demo Applicant', 'email' => 'applicant@example.test', 'phone' => '01700000006']);
        Organization::factory()->business()->withMember($applicant)->create(['name' => 'Pending Café']);
    }
}

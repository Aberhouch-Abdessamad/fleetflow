<?php

namespace Database\Seeders;

use App\Models\User;
use App\Services\Tenant\CompanyOnboardingService;
use App\Support\RoleRegistry;
use Illuminate\Database\Seeder;

/**
 * Development seed: roles/permissions, one super admin and one demo company
 * whose owner is Company Admin. Passwords are the dev-only value "password".
 * No operational data (vehicles, deliveries…) is invented here.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        RoleRegistry::sync();

        $admin = User::firstOrCreate(['email' => 'admin@fleetflow.test'], ['name' => 'Platform Admin', 'password' => 'password']);
        $admin->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $owner = User::firstOrCreate(['email' => 'owner@fleetflow.test'], ['name' => 'Demo Owner', 'password' => 'password']);
        $owner->forceFill(['email_verified_at' => now()])->save();

        if ($owner->companies()->doesntExist()) {
            app(CompanyOnboardingService::class)->create($owner, [
                'name' => 'Atlas Logistics',
                'contact_email' => 'ops@atlas-logistics.test',
                'phone' => '+212 522 000 000',
                'country' => 'MA',
                'city' => 'Casablanca',
                'address' => 'Boulevard Zerktouni',
                'timezone' => 'Africa/Casablanca',
                'currency' => 'MAD',
            ]);
        }
    }
}

<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use App\Models\VehicleType;
use App\Support\RoleRegistry;
use Illuminate\Database\Seeder;

/** Roles, platform vehicle types and subscription plans. Idempotent. */
class ReferenceDataSeeder extends Seeder
{
    public function run(): void
    {
        RoleRegistry::sync();

        $types = [
            ['Motorcycle', 'motorcycle', 'A', 40],
            ['Car', 'car', 'B', 400],
            ['Van', 'van', 'B', 1500],
            ['Pickup', 'pickup', 'B', 1000],
            ['Truck', 'truck', 'C', 7000],
            ['Heavy Truck', 'heavy_truck', 'CE', 24000],
            ['Refrigerated Truck', 'refrigerated_truck', 'C', 6000],
        ];
        foreach ($types as [$name, $code, $license, $cap]) {
            $t = VehicleType::withoutGlobalScopes()->firstOrNew(['company_id' => null, 'code' => $code]);
            $t->fill(['name' => $name, 'required_license' => $license, 'default_capacity' => $cap, 'capacity_unit' => 'kg'])->save();
        }

        $plans = [
            ['starter', 'Starter', 'For small fleets getting started.', 49, 14, 5, 10, 5, 500, [], 1, false],
            ['professional', 'Professional', 'GPS tracking, route planning and advanced reports.', 149, 14, 25, 50, 25, 5000, ['gps_tracking', 'route_planning', 'route_optimization', 'advanced_reports'], 2, true],
            ['enterprise', 'Enterprise', 'Custom limits, API integrations and priority support.', 499, 30, null, null, null, null, ['gps_tracking', 'route_planning', 'route_optimization', 'advanced_reports', 'api_integrations', 'priority_support'], 3, false],
        ];
        foreach ($plans as [$code, $name, $desc, $price, $trial, $veh, $drv, $usr, $del, $features, $sort, $default]) {
            SubscriptionPlan::updateOrCreate(['code' => $code], [
                'name' => $name, 'description' => $desc, 'price_monthly' => $price, 'currency' => 'USD', 'trial_days' => $trial,
                'max_vehicles' => $veh, 'max_drivers' => $drv, 'max_users' => $usr, 'max_monthly_deliveries' => $del,
                'features' => $features, 'is_active' => true, 'is_default' => $default, 'sort_order' => $sort,
            ]);
        }
    }
}

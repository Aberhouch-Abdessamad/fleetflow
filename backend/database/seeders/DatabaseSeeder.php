<?php

namespace Database\Seeders;

use App\Enums\DeliveryStatus;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleType;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Dispatch\AssignmentService;
use App\Services\Expense\ExpenseService;
use App\Services\Fleet\VehicleAssignmentService;
use App\Services\Fuel\FuelService;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenant\CompanyOnboardingService;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Development seed. Reference data + one demo company with accounts for every
 * role and a small, internally-consistent operational history created through
 * the real services (so histories, expenses and statuses all line up).
 *
 * GPS positions are NOT seeded — live tracking only shows real device data.
 * All passwords are the dev-only value "password". Never run in production.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(ReferenceDataSeeder::class);

        $admin = User::firstOrCreate(['email' => 'admin@fleetflow.test'], ['name' => 'Platform Admin', 'password' => 'password']);
        $admin->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();

        $owner = User::firstOrCreate(['email' => 'owner@fleetflow.test'], ['name' => 'Amina El Idrissi', 'password' => 'password']);
        $owner->forceFill(['email_verified_at' => now()])->save();
        if ($owner->companies()->exists()) {
            return; // already seeded
        }

        $company = app(CompanyOnboardingService::class)->create($owner, [
            'name' => 'Atlas Logistics', 'contact_email' => 'ops@atlas-logistics.test', 'phone' => '+212 522 000 000',
            'country' => 'MA', 'city' => 'Casablanca', 'address' => 'Boulevard Zerktouni', 'timezone' => 'Africa/Casablanca', 'currency' => 'MAD',
            'registration_number' => 'RC 412233',
        ]);
        // Demo company on the Enterprise plan so every module is enabled.
        app(SubscriptionService::class)->changePlan($company, SubscriptionPlan::where('code', 'enterprise')->first(), $admin, 'active', null, 'Demo workspace');

        app(TenantContext::class)->runAs($company, fn () => $this->demo($company, $owner));
    }

    private function demo(Company $company, User $owner): void
    {
        $tenant = app(TenantContext::class);
        $member = function (string $email, string $name, string $role) use ($company, $tenant) {
            $u = User::firstOrCreate(['email' => $email], ['name' => $name, 'password' => 'password']);
            $u->forceFill(['email_verified_at' => now(), 'current_company_id' => $company->id])->save();
            $company->users()->syncWithoutDetaching([$u->id => ['status' => 'active', 'joined_at' => now()]]);
            $tenant->runAs($company, fn () => $u->unsetRelation('roles')->syncRoles([$role]));

            return $u;
        };
        $member('fleet@fleetflow.test', 'Omar Fassi', 'fleet_manager');
        $dispatcher = $member('dispatch@fleetflow.test', 'Salma Berrada', 'dispatcher');
        $member('finance@fleetflow.test', 'Hicham Tazi', 'accountant');

        $type = fn ($code) => VehicleType::where('code', $code)->value('id');
        $vehicles = collect([
            ['12345-A-6', 'Renault', 'Master', 'van', 1500, 84210],
            ['23456-B-6', 'Ford', 'Transit', 'van', 1400, 61200],
            ['34567-A-1', 'Isuzu', 'NPR', 'truck', 4500, 132900],
            ['45678-D-6', 'Peugeot', 'Partner', 'car', 650, 40210],
            ['56789-H-6', 'Mercedes-Benz', 'Sprinter', 'van', 1600, 20500],
            ['67890-A-6', 'Iveco', 'Eurocargo', 'refrigerated_truck', 6000, 99800],
        ])->map(fn ($v) => Vehicle::create([
            'vehicle_type_id' => $type($v[3]), 'registration_number' => $v[0], 'make' => $v[1], 'model' => $v[2],
            'year' => rand(2018, 2025), 'fuel_type' => 'diesel', 'capacity' => $v[4], 'odometer' => $v[5],
            'insurance_expiry_date' => now()->addDays(rand(10, 300))->toDateString(),
            'registration_expiry_date' => now()->addDays(rand(60, 400))->toDateString(),
            'next_service_date' => now()->addDays(rand(-5, 60))->toDateString(),
        ]));

        $drivers = collect([
            ['Youssef', 'Amrani', 'C', 'driver@fleetflow.test'],
            ['Khadija', 'Benali', 'B', null],
            ['Mehdi', 'Ouazzani', 'CE', null],
            ['Nadia', 'Chraibi', 'B', null],
            ['Rachid', 'Lahlou', 'C', null],
        ])->map(function ($d, $i) use ($member) {
            $driver = Driver::create([
                'first_name' => $d[0], 'last_name' => $d[1], 'email' => strtolower($d[0]).'@atlas.test', 'phone' => '+21266100000'.$i,
                'license_number' => 'MA-'.(700000 + $i), 'license_type' => $d[2], 'license_expiry_date' => now()->addMonths(rand(2, 30))->toDateString(),
                'employment_date' => now()->subMonths(rand(6, 40))->toDateString(),
            ]);
            if ($d[3]) {
                $driver->forceFill(['user_id' => $member($d[3], "{$d[0]} {$d[1]}", 'driver')->id])->save();
            }

            return $driver;
        });

        $va = app(VehicleAssignmentService::class);
        foreach ([[0, 0], [1, 1], [2, 2], [3, 3]] as [$v, $d]) {
            $va->assign($vehicles[$v], $drivers[$d], $owner);
        }

        $customers = collect(['Marjane Market', 'Electroplanet', 'Pharmacie Centrale', 'Café Atlas', 'Decathlon Maroc'])
            ->map(fn ($n) => Customer::create(['name' => $n, 'email' => strtolower(str_replace(' ', '', $n)).'@example.com', 'phone' => '+2125220'.rand(10000, 99999), 'customer_type' => 'business']));

        $places = [
            ['Maarif, Casablanca', 33.5883, -7.6324, 'Casablanca'], ['Ain Diab, Casablanca', 33.5925, -7.6750, 'Casablanca'],
            ['Agdal, Rabat', 33.9981, -6.8538, 'Rabat'], ['Hay Riad, Rabat', 33.9591, -6.8770, 'Rabat'],
            ['Mohammedia Centre', 33.6866, -7.3830, 'Mohammedia'], ['Bouskoura', 33.4490, -7.6486, 'Bouskoura'],
            ['Sidi Maarouf, Casablanca', 33.5300, -7.6420, 'Casablanca'], ['Temara', 33.9287, -6.9063, 'Temara'],
        ];
        $deliveries = app(DeliveryService::class);
        $status = app(DeliveryStatusService::class);
        $assign = app(AssignmentService::class);

        // 40 historical deliveries over the last 30 days through the real lifecycle.
        for ($i = 0; $i < 40; $i++) {
            $p = $places[$i % count($places)];
            $created = now()->subDays(rand(1, 30))->setTime(rand(7, 11), rand(0, 59));
            Carbon::setTestNow($created);
            [$d] = $deliveries->create($company, [
                'customer_id' => $customers[$i % 5]->id,
                'pickup_address' => 'Atlas Depot, Sidi Bernoussi, Casablanca', 'pickup_latitude' => 33.6110, 'pickup_longitude' => -7.4990,
                'delivery_address' => $p[0], 'delivery_latitude' => $p[1], 'delivery_longitude' => $p[2], 'delivery_city' => $p[3],
                'recipient_name' => fake()->name(), 'recipient_phone' => '+2126'.rand(10000000, 99999999),
                'scheduled_delivery_at' => $created->copy()->addHours(rand(3, 8)), 'priority' => ['normal', 'normal', 'high', 'low', 'urgent'][rand(0, 4)],
                'delivery_fee' => [45, 60, 75, 90, 120][rand(0, 4)], 'requires_proof' => false,
                'items' => [['description' => 'Parcel', 'quantity' => rand(1, 6), 'weight' => rand(2, 25)]],
            ], $dispatcher);
            $k = $i % 4;
            $a = $assign->assign($d, $drivers[$k], $vehicles[$k], $dispatcher);
            Carbon::setTestNow($created->copy()->addMinutes(10));
            $assign->respond($a, $drivers[$k]->fresh(), true);
            Carbon::setTestNow($created->copy()->addMinutes(40));
            $status->transition($d, DeliveryStatus::PickedUp, $drivers[$k]->user);
            Carbon::setTestNow($created->copy()->addMinutes(55));
            $status->transition($d, DeliveryStatus::InTransit, $drivers[$k]->user);
            Carbon::setTestNow($created->copy()->addMinutes(rand(120, 420)));
            if ($i % 9 === 4) {
                $status->transition($d, DeliveryStatus::Failed, $drivers[$k]->user, ['Recipient unavailable', 'Address not found', 'Refused by customer'][rand(0, 2)]);
            } else {
                $status->transition($d, DeliveryStatus::Arrived, $drivers[$k]->user);
                $status->transition($d, DeliveryStatus::Delivered, $drivers[$k]->user);
                if (rand(0, 2) === 0) {
                    $d->forceFill(['customer_rating' => rand(4, 5)])->save();
                }
            }
            Delivery::whereKey($d->id)->update(['created_at' => $created]);
        }
        Carbon::setTestNow();

        // Today's open work for the dispatch board.
        foreach (range(1, 7) as $i) {
            $p = $places[$i % count($places)];
            [$d] = $deliveries->create($company, [
                'customer_id' => $customers[$i % 5]->id,
                'pickup_address' => 'Atlas Depot, Sidi Bernoussi, Casablanca', 'pickup_latitude' => 33.6110, 'pickup_longitude' => -7.4990,
                'delivery_address' => $p[0], 'delivery_latitude' => $p[1], 'delivery_longitude' => $p[2], 'delivery_city' => $p[3],
                'recipient_name' => fake()->name(), 'recipient_phone' => '+2126'.rand(10000000, 99999999), 'recipient_email' => null,
                'scheduled_delivery_at' => now()->addHours($i + 1), 'priority' => $i === 1 ? 'urgent' : 'normal',
                'delivery_fee' => 60, 'cod_amount' => $i === 3 ? 350 : 0,
                'items' => [['description' => 'Boxes', 'quantity' => 2, 'weight' => 8]],
            ], $dispatcher);
            if ($i <= 3) {
                $a = $assign->assign($d, $drivers[0], $vehicles[0], $dispatcher);
                if ($i === 1) {
                    $assign->respond($a, $drivers[0]->fresh(), true);
                }
            }
        }

        // Fuel, maintenance, expenses.
        $fuel = app(FuelService::class);
        foreach ($vehicles->take(4) as $idx => $v) {
            $odo = $v->odometer - 1800;
            foreach (range(4, 1) as $w) {
                $odo += rand(350, 520);
                $fuel->create(['vehicle_id' => $v->id, 'driver_id' => $drivers[$idx]->id, 'fuel_type' => 'diesel', 'quantity' => rand(35, 60),
                    'unit_price' => 12.6 + rand(0, 40) / 100, 'odometer' => $odo, 'full_tank' => true, 'station_name' => ['Afriquia', 'Shell', 'TotalEnergies'][rand(0, 2)],
                    'recorded_at' => now()->subWeeks($w)->toDateTimeString()], $owner);
            }
        }
        $maintenance = app(MaintenanceService::class);
        $maintenance->create(['vehicle_id' => $vehicles[2]->id, 'maintenance_type' => 'oil_change', 'title' => 'Oil & filter change', 'service_provider' => 'Garage Atlas', 'scheduled_date' => now()->subDays(12)->toDateString(), 'completed_date' => now()->subDays(12)->toDateString(), 'cost' => 950, 'status' => 'completed', 'next_service_date' => now()->addMonths(4)->toDateString()], $owner);
        $maintenance->create(['vehicle_id' => $vehicles[5]->id, 'maintenance_type' => 'brake_inspection', 'title' => 'Brake pads replacement', 'service_provider' => 'Iveco Service', 'scheduled_date' => now()->toDateString(), 'cost' => 2400, 'status' => 'in_progress'], $owner);
        $maintenance->create(['vehicle_id' => $vehicles[1]->id, 'maintenance_type' => 'regular_service', 'title' => '60,000 km service', 'scheduled_date' => now()->addDays(6)->toDateString(), 'cost' => 1800], $owner);
        $maintenance->create(['vehicle_id' => $vehicles[4]->id, 'maintenance_type' => 'tire_replacement', 'title' => 'Front tyres', 'scheduled_date' => now()->addDays(15)->toDateString(), 'cost' => 3200], $owner);

        $expenses = app(ExpenseService::class);
        $finance = User::where('email', 'finance@fleetflow.test')->first();
        foreach ([['tolls', 'Autoroute Casa–Rabat', 74], ['parking', 'Parking Agdal', 20], ['insurance', 'Fleet insurance instalment', 4500], ['repairs', 'Side mirror replacement', 650]] as $i => [$cat, $desc, $amt]) {
            $e = $expenses->create(['category' => $cat, 'description' => $desc, 'amount' => $amt, 'expense_date' => now()->subDays($i * 5 + 1)->toDateString(), 'vehicle_id' => $vehicles[$i]->id], $owner);
            if ($i < 3) {
                $expenses->review($e, $finance, true);
            }
        }
        // Approve generated fuel/maintenance expenses older than a week.
        Expense::whereNotNull('source_type')->where('expense_date', '<', now()->subWeek())->get()->each(fn ($e) => $expenses->review($e, $finance, true));
    }
}

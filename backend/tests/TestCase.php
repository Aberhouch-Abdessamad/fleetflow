<?php

namespace Tests;

use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryService;
use App\Services\Dispatch\AssignmentService;
use App\Services\Tenant\CompanyOnboardingService;
use App\Support\Tenancy\TenantContext;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ReferenceDataSeeder::class);
    }

    protected function companyPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Atlas Logistics',
            'contact_email' => 'ops@atlas.test',
            'country' => 'ma',
            'city' => 'Casablanca',
            'timezone' => 'Africa/Casablanca',
            'currency' => 'mad',
        ], $overrides);
    }

    /** Creates a user who owns (Company Admin) a fresh company. */
    protected function companyOwner(array $companyOverrides = []): array
    {
        $user = User::factory()->create();
        $company = app(CompanyOnboardingService::class)->create($user, $this->companyPayload([
            'name' => 'Company '.uniqid(),
            ...$companyOverrides,
        ]));
        app(TenantContext::class)->set(null);

        return [$user->fresh(), $company];
    }

    /** Re-read a model bypassing the tenant scope (tests run outside a request). */
    protected function reload(Model $m): Model
    {
        $q = $m::withoutGlobalScopes();

        return in_array(SoftDeletes::class, class_uses_recursive($m)) ? $q->withTrashed()->find($m->getKey()) : $q->find($m->getKey());
    }

    protected function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** Runs $fn inside $company's tenant context and resets afterwards. */
    protected function within(Company $company, callable $fn): mixed
    {
        return $this->tenant()->runAs($company, $fn);
    }

    /** New user with $role in $company (creates the company if null). */
    protected function member(string $role, ?Company $company = null): array
    {
        $company ??= $this->companyOwner()[1];
        $user = $this->addMember($company, User::factory()->create(), $role);

        return [$user, $company];
    }

    protected function vehicle(Company $company, array $attrs = []): Vehicle
    {
        return $this->within($company, fn () => Vehicle::factory()->create($attrs));
    }

    /** Driver profile, optionally linked to a user account with the driver role. */
    protected function driver(Company $company, array $attrs = [], bool $withUser = false): Driver
    {
        $driver = $this->within($company, fn () => Driver::factory()->create($attrs));
        if ($withUser) {
            $user = $this->addMember($company, User::factory()->create(), 'driver');
            $driver->forceFill(['user_id' => $user->id])->save();
        }

        return $driver->fresh();
    }

    protected function deliveryPayload(array $overrides = []): array
    {
        return array_merge([
            'pickup_address' => 'Depot, Casablanca',
            'pickup_latitude' => 33.5731, 'pickup_longitude' => -7.5898,
            'delivery_address' => '12 Rue Atlas, Rabat',
            'delivery_latitude' => 34.0209, 'delivery_longitude' => -6.8416,
            'delivery_city' => 'Rabat',
            'recipient_name' => 'Youssef Amrani',
            'recipient_phone' => '+212600000000',
            'scheduled_delivery_at' => now()->addDay()->toIso8601String(),
            'delivery_fee' => '45.50',
            'items' => [['description' => 'Boxes', 'quantity' => 4, 'weight' => 12.5]],
        ], $overrides);
    }

    protected function delivery(Company $company, array $overrides = [], ?User $by = null): Delivery
    {
        return $this->within($company, fn () => app(DeliveryService::class)
            ->create($company, $this->deliveryPayload($overrides), $by)[0]);
    }

    /** Assigns via the real service and has the driver accept. */
    protected function assigned(Company $company, Delivery $d, Driver $driver, Vehicle $vehicle, bool $accept = true): Delivery
    {
        return $this->within($company, function () use ($d, $driver, $vehicle, $accept, $company) {
            $assignment = app(AssignmentService::class)->assign($d, $driver, $vehicle, $company->owner);
            if ($accept) {
                app(AssignmentService::class)->respond($assignment, Driver::find($driver->id), true);
            }

            return $d->fresh();
        });
    }

    /** Adds $user to $company with the given company role. */
    protected function addMember(Company $company, User $user, string $role): User
    {
        $company->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        app(TenantContext::class)->runAs($company, fn () => $user->unsetRelation('roles')->assignRole($role));

        return $user->fresh();
    }
}

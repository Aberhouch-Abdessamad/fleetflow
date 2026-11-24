<?php

namespace Tests;

use App\Models\Company;
use App\Models\User;
use App\Services\Tenant\CompanyOnboardingService;
use App\Support\RoleRegistry;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RoleRegistry::sync();
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

    /** Adds $user to $company with the given company role. */
    protected function addMember(Company $company, User $user, string $role): User
    {
        $company->users()->attach($user->id, ['status' => 'active', 'joined_at' => now()]);
        app(TenantContext::class)->runAs($company, fn () => $user->unsetRelation('roles')->assignRole($role));

        return $user->fresh();
    }
}

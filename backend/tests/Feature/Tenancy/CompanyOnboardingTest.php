<?php

namespace Tests\Feature\Tenancy;

use App\Models\Company;
use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CompanyOnboardingTest extends TestCase
{
    public function test_registering_user_creates_company_and_becomes_company_admin(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/v1/companies', $this->companyPayload())
            ->assertCreated()
            ->assertJsonPath('data.name', 'Atlas Logistics')
            ->assertJsonPath('data.country', 'MA')
            ->assertJsonPath('data.currency', 'MAD')
            ->assertJsonPath('data.status', 'active');

        $company = Company::firstOrFail();
        $this->assertSame($user->id, $company->owner_id);
        $this->assertTrue($user->fresh()->isMemberOf($company));

        $this->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.current_company.id', $company->public_id)
            ->assertJsonPath('data.roles.0', 'company_admin')
            ->assertJsonFragment(['vehicles.manage']);

        $this->assertDatabaseHas('activity_logs', ['action' => 'company.created', 'company_id' => $company->id]);
    }

    public function test_internal_ids_are_never_exposed(): void
    {
        [$owner, $company] = $this->companyOwner();
        Sanctum::actingAs($owner);

        $id = $this->getJson('/api/v1/companies/current')->json('data.id');
        $this->assertSame($company->public_id, $id);
        $this->assertNotSame((string) $company->id, (string) $id);
    }

    public function test_company_creation_is_validated(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/v1/companies', ['name' => 'X', 'country' => 'Morocco', 'timezone' => 'Mars/Base', 'currency' => 'DOLLARS'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name', 'contact_email', 'country', 'city', 'timezone', 'currency']);
    }

    public function test_tenant_routes_require_a_company(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/v1/companies/current')->assertForbidden()->assertJsonPath('code', 'tenant_missing');
    }

    public function test_owner_can_update_company_but_cannot_move_status(): void
    {
        [$owner, $company] = $this->companyOwner();
        Sanctum::actingAs($owner);

        $this->putJson('/api/v1/companies/current', ['city' => 'Rabat', 'status' => 'suspended', 'owner_id' => 999])
            ->assertOk()->assertJsonPath('data.city', 'Rabat')->assertJsonPath('data.status', 'active');

        $this->assertSame($owner->id, $company->fresh()->owner_id);
    }

    public function test_members_without_permission_cannot_update_company(): void
    {
        [, $company] = $this->companyOwner();
        $driver = $this->addMember($company, User::factory()->create(), 'driver');
        Sanctum::actingAs($driver);

        $this->getJson('/api/v1/companies/current')->assertOk();
        $this->putJson('/api/v1/companies/current', ['city' => 'Rabat'])->assertForbidden();
    }
}

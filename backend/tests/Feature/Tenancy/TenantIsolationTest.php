<?php

namespace Tests\Feature\Tenancy;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    public function test_user_cannot_select_a_company_they_do_not_belong_to(): void
    {
        [$alice] = $this->companyOwner();
        [, $bobCompany] = $this->companyOwner();
        Sanctum::actingAs($alice);

        $this->withHeader('X-Company', $bobCompany->public_id)
            ->getJson('/api/v1/companies/current')
            ->assertForbidden()
            ->assertJsonPath('code', 'tenant_forbidden');
    }

    public function test_user_cannot_switch_to_a_foreign_company(): void
    {
        [$alice] = $this->companyOwner();
        [, $bobCompany] = $this->companyOwner();
        Sanctum::actingAs($alice);

        $this->postJson("/api/v1/companies/{$bobCompany->public_id}/switch")->assertNotFound();
        $this->assertNotSame($bobCompany->id, $alice->fresh()->current_company_id);
    }

    public function test_request_body_company_id_is_ignored(): void
    {
        [$alice, $aliceCompany] = $this->companyOwner();
        [, $bobCompany] = $this->companyOwner();
        Sanctum::actingAs($alice);

        $this->putJson('/api/v1/companies/current?company_id='.$bobCompany->id, ['company_id' => $bobCompany->id, 'city' => 'Tangier'])
            ->assertOk()->assertJsonPath('data.id', $aliceCompany->public_id);

        $this->assertSame('Tangier', $aliceCompany->fresh()->city);
        $this->assertSame('Casablanca', $bobCompany->fresh()->city);
    }

    public function test_roles_are_scoped_per_company(): void
    {
        [, $companyA] = $this->companyOwner();
        [, $companyB] = $this->companyOwner();
        $user = User::factory()->create();
        $this->addMember($companyA, $user, 'company_admin');
        $this->addMember($companyB, $user, 'driver');

        $tenant = app(TenantContext::class);
        $this->assertTrue($tenant->runAs($companyA, fn () => $user->fresh()->can('vehicles.manage')));
        $this->assertFalse($tenant->runAs($companyB, fn () => $user->fresh()->can('vehicles.manage')));

        Sanctum::actingAs($user);
        $this->withHeader('X-Company', $companyB->public_id)->getJson('/api/v1/auth/me')
            ->assertJsonPath('data.roles', ['driver']);
        $this->withHeader('X-Company', $companyB->public_id)->putJson('/api/v1/companies/current', ['city' => 'Fes'])
            ->assertForbidden();
    }

    public function test_suspended_company_is_blocked_from_operating(): void
    {
        [$owner, $company] = $this->companyOwner();
        $company->forceFill(['status' => 'suspended', 'suspended_at' => now()])->save();
        Sanctum::actingAs($owner);

        $this->getJson('/api/v1/companies/current')->assertStatus(423)->assertJsonPath('code', 'tenant_suspended');
        // Identity endpoint still works so the SPA can explain the suspension.
        $this->getJson('/api/v1/auth/me')->assertOk();
    }

    public function test_disabled_membership_loses_access(): void
    {
        [, $company] = $this->companyOwner();
        $user = $this->addMember($company, User::factory()->create(), 'dispatcher');
        $company->users()->updateExistingPivot($user->id, ['status' => 'disabled']);
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/companies/current')->assertForbidden();
    }
}

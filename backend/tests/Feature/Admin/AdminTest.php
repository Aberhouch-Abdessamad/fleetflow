<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminTest extends TestCase
{
    public function test_only_super_admins_reach_admin_endpoints(): void
    {
        [$owner] = $this->companyOwner();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/admin/dashboard')->assertForbidden();
        $this->getJson('/api/v1/admin/companies')->assertForbidden();
    }

    public function test_super_admin_dashboard_and_company_suspension_blocks_tenant_operations(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [$owner, $company] = $this->companyOwner();
        $this->vehicle($company);
        Sanctum::actingAs($admin);

        $this->getJson('/api/v1/admin/dashboard')->assertOk()->assertJsonPath('data.total_companies', 1)->assertJsonPath('data.total_vehicles', 1);
        $this->getJson("/api/v1/admin/companies/{$company->public_id}")->assertOk()->assertJsonPath('data.usage.vehicles', 1);
        $this->assertDatabaseHas('activity_logs', ['action' => 'admin.company_viewed', 'company_id' => $company->id]);

        $this->patchJson("/api/v1/admin/companies/{$company->public_id}/suspend", [])->assertStatus(422);
        $this->patchJson("/api/v1/admin/companies/{$company->public_id}/suspend", ['reason' => 'Unpaid'])->assertOk()->assertJsonPath('data.status', 'suspended');

        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/vehicles')->assertStatus(423)->assertJsonPath('code', 'tenant_suspended');

        Sanctum::actingAs($admin);
        $this->patchJson("/api/v1/admin/companies/{$company->public_id}/activate")->assertOk();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/vehicles')->assertOk();
    }

    public function test_plans_and_subscription_changes(): void
    {
        $admin = User::factory()->superAdmin()->create();
        [, $company] = $this->companyOwner();
        Sanctum::actingAs($admin);

        $this->postJson('/api/v1/admin/plans', ['name' => 'Growth', 'code' => 'growth', 'price_monthly' => 99, 'currency' => 'USD', 'max_vehicles' => 15, 'features' => ['gps_tracking', 'bogus']])->assertStatus(422);
        $this->postJson('/api/v1/admin/plans', ['name' => 'Growth', 'code' => 'growth', 'price_monthly' => 99, 'currency' => 'USD', 'max_vehicles' => 15, 'features' => ['gps_tracking']])->assertCreated();

        $this->putJson("/api/v1/admin/companies/{$company->public_id}/subscription", ['plan' => 'growth', 'status' => 'active'])->assertOk();
        $this->assertSame('growth', $company->fresh()->currentSubscription->plan->code);
        $this->assertSame(2, $company->subscriptions()->count()); // history kept
        $this->getJson('/api/v1/admin/dashboard')->assertJsonPath('data.mrr', '99.00');
    }

    public function test_user_suspension_revokes_tokens(): void
    {
        $admin = User::factory()->superAdmin()->create();
        $user = User::factory()->create();
        $user->createToken('x');
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/admin/users/{$user->id}/status", ['status' => 'suspended'])->assertOk();
        $this->assertSame(0, $user->tokens()->count());
        $this->patchJson("/api/v1/admin/users/{$admin->id}/status", ['status' => 'suspended'])->assertStatus(422);
    }
}

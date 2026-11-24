<?php

namespace Tests\Feature\Subscription;

use App\Models\SubscriptionPlan;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    public function test_new_company_gets_a_trial_and_gps_is_gated_on_starter(): void
    {
        [$owner, $company] = $this->companyOwner();
        Sanctum::actingAs($owner);
        $this->getJson('/api/v1/companies/current/subscription')->assertOk()->assertJsonPath('data.status', 'trialing')->assertJsonPath('data.plan.code', 'professional');

        $company->currentSubscription->forceFill(['subscription_plan_id' => SubscriptionPlan::where('code', 'starter')->value('id')])->save();
        $driver = $this->driver($company, [], true);
        Sanctum::actingAs($driver->user);
        $this->postJson('/api/v1/driver/shift/start')->assertOk();
        $this->postJson('/api/v1/tracking/location', ['latitude' => 33.5, 'longitude' => -7.5, 'recorded_at' => now()->toIso8601String()])
            ->assertStatus(402)->assertJsonPath('code', 'plan_feature_unavailable');
    }

    public function test_expired_subscription_blocks_growth(): void
    {
        [$owner, $company] = $this->companyOwner();
        $company->currentSubscription->forceFill(['trial_ends_at' => now()->subDay()])->save();
        $this->artisan('fleetflow:expire-subscriptions')->assertSuccessful();
        Sanctum::actingAs($owner);

        $this->postJson('/api/v1/drivers', ['first_name' => 'A', 'last_name' => 'B', 'phone' => '1', 'license_number' => 'L', 'license_type' => 'B', 'license_expiry_date' => now()->addYear()->toDateString()])
            ->assertStatus(402)->assertJsonPath('code', 'subscription_inactive');
    }
}

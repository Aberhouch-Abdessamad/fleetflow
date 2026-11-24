<?php

namespace Tests\Feature\Tracking;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\VehicleLocation;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Fleet\VehicleAssignmentService;
use App\Services\Tenant\CompanySettings;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    private function ping(array $o = []): array
    {
        return array_merge(['latitude' => 33.5731, 'longitude' => -7.5898, 'speed' => 42, 'heading' => 90, 'accuracy' => 8, 'recorded_at' => now()->toIso8601String()], $o);
    }

    public function test_driver_on_shift_reports_location_and_identity_is_derived(): void
    {
        [, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $this->within($company, fn () => app(VehicleAssignmentService::class)->assign($vehicle, $driver, $company->owner));
        Sanctum::actingAs($driver->user);

        $this->postJson('/api/v1/tracking/location', $this->ping())->assertStatus(403)->assertJsonPath('code', 'not_on_duty');
        $this->postJson('/api/v1/driver/shift/start')->assertOk();

        $this->postJson('/api/v1/tracking/location', $this->ping(['driver_id' => 999]))->assertStatus(422)->assertJsonValidationErrors('driver_id');
        $this->postJson('/api/v1/tracking/location', $this->ping())->assertCreated()->assertJsonPath('data.status', 'stored');

        $loc = VehicleLocation::withoutGlobalScopes()->first();
        $this->assertSame($driver->id, $loc->driver_id);
        $this->assertSame($vehicle->id, $loc->vehicle_id);
        $this->assertSame($company->id, $loc->company_id);
        $this->assertEqualsWithDelta(33.5731, $this->reload($vehicle)->last_latitude, 0.0001);
    }

    public function test_invalid_duplicate_and_throttled_payloads(): void
    {
        [, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        Sanctum::actingAs($driver->user);
        $this->postJson('/api/v1/driver/shift/start')->assertOk();

        $this->postJson('/api/v1/tracking/location', $this->ping(['latitude' => 120]))->assertStatus(422);
        $this->postJson('/api/v1/tracking/location', $this->ping(['latitude' => 0, 'longitude' => 0]))->assertStatus(422)->assertJsonPath('code', 'invalid_coordinates');
        $this->postJson('/api/v1/tracking/location', $this->ping(['recorded_at' => now()->addHour()->toIso8601String()]))->assertStatus(422)->assertJsonPath('code', 'invalid_timestamp');

        $t = now()->subMinute()->toIso8601String();
        $this->postJson('/api/v1/tracking/location', $this->ping(['recorded_at' => $t]))->assertCreated();
        Cache::flush(); // bypass the interval throttle to hit the unique index
        $this->postJson('/api/v1/tracking/location', $this->ping(['recorded_at' => $t]))->assertOk()->assertJsonPath('data.status', 'duplicate');
        $this->postJson('/api/v1/tracking/location', $this->ping(['recorded_at' => now()->subSeconds(30)->toIso8601String()]))->assertCreated();
        $this->postJson('/api/v1/tracking/location', $this->ping(['recorded_at' => now()->subSeconds(28)->toIso8601String()]))->assertOk()->assertJsonPath('data.status', 'throttled');
        $this->assertSame(2, VehicleLocation::withoutGlobalScopes()->count());
    }

    public function test_unauthorised_users_cannot_submit_or_view_gps(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        [$accountant] = $this->member('accountant', $company);
        Sanctum::actingAs($dispatcher);
        $this->postJson('/api/v1/tracking/location', $this->ping())->assertForbidden();
        $this->getJson('/api/v1/tracking/vehicles')->assertOk();
        Sanctum::actingAs($accountant);
        $this->getJson('/api/v1/tracking/vehicles')->assertForbidden();
    }

    public function test_public_tracking_exposes_only_approved_fields(): void
    {
        [, $company] = $this->member('company_admin');
        $driver = $this->driver($company, ['first_name' => 'Hamza', 'phone' => '+212699999999'], true);
        $d = $this->assigned($company, $this->delivery($company), $driver, $this->vehicle($company));
        $token = Delivery::withoutGlobalScopes()->find($d->id)->tracking_token;

        $res = $this->getJson("/api/v1/tracking/public/{$token}")->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.driver.first_name', 'Hamza');
        $json = json_encode($res->json());
        foreach (['+212699999999', 'assigned_driver_id', '"id"', 'company_id', 'tracking_token', 'otp'] as $secret) {
            $this->assertStringNotContainsString($secret, $json);
        }

        $this->getJson('/api/v1/tracking/public/'.str_repeat('a', 48))->assertNotFound();
        app(CompanySettings::class)->update($company, ['public_tracking_enabled' => false]);
        $this->getJson("/api/v1/tracking/public/{$token}")->assertNotFound();
    }

    public function test_customer_can_rate_after_delivery(): void
    {
        [, $company] = $this->member('company_admin');
        $d = $this->delivery($company, ['requires_proof' => false]);
        $driver = $this->driver($company, [], true);
        $this->assigned($company, $d, $driver, $this->vehicle($company));
        $this->within($company, function () use ($d) {
            foreach (['picked_up', 'in_transit', 'arrived', 'delivered'] as $s) {
                app(DeliveryStatusService::class)->transition(Delivery::find($d->id), DeliveryStatus::from($s), null);
            }
        });
        $token = Delivery::withoutGlobalScopes()->find($d->id)->tracking_token;

        $this->postJson("/api/v1/tracking/public/{$token}/rating", ['rating' => 5])->assertOk();
        $this->postJson("/api/v1/tracking/public/{$token}/rating", ['rating' => 4])->assertStatus(409);
        $this->assertSame(5, Delivery::withoutGlobalScopes()->find($d->id)->customer_rating);
    }
}

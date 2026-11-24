<?php

namespace Tests\Feature\Driver;

use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DriverTest extends TestCase
{
    public function test_fleet_manager_manages_drivers(): void
    {
        [$fm] = $this->member('fleet_manager');
        Sanctum::actingAs($fm);

        $id = $this->postJson('/api/v1/drivers', [
            'first_name' => 'Karim', 'last_name' => 'Bennani', 'phone' => '+212611111111',
            'license_number' => 'LIC-1', 'license_type' => 'C', 'license_expiry_date' => now()->addYear()->toDateString(),
            'national_id' => 'AB123456',
        ])->assertCreated()->assertJsonPath('data.name', 'Karim Bennani')->json('data.id');

        $this->putJson("/api/v1/drivers/{$id}/availability", ['availability' => 'on_leave', 'reason' => 'Holiday'])
            ->assertOk()->assertJsonPath('data.availability', 'on_leave');
        $this->getJson("/api/v1/drivers/{$id}/availability")->assertOk()->assertJsonPath('data.0.status', 'on_leave');
        $this->getJson("/api/v1/drivers/{$id}/performance")->assertOk()->assertJsonPath('data.total_deliveries', 0)->assertJsonPath('data.on_time_rate', null);
    }

    public function test_private_driver_information_is_hidden_from_dispatchers(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, ['national_id' => 'SECRET-ID', 'address' => 'Home address']);
        Sanctum::actingAs($dispatcher);

        $this->getJson("/api/v1/drivers/{$driver->id}")->assertOk()
            ->assertJsonMissingPath('data.national_id')->assertJsonMissingPath('data.license_number')->assertJsonMissingPath('data.address');
        $this->getJson("/api/v1/drivers/{$driver->id}/documents")->assertForbidden();
        $this->postJson('/api/v1/drivers', [])->assertForbidden();
    }

    public function test_driver_with_active_deliveries_cannot_be_deactivated(): void
    {
        [$admin, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        $this->assigned($company, $this->delivery($company), $driver, $this->vehicle($company));
        Sanctum::actingAs($admin);

        $this->patchJson("/api/v1/drivers/{$driver->id}/status", ['status' => 'inactive'])->assertStatus(422)->assertJsonPath('code', 'driver_busy');
    }
}

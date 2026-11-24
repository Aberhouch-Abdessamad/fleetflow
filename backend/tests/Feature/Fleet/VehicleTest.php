<?php

namespace Tests\Feature\Fleet;

use App\Models\SubscriptionPlan;
use App\Models\Vehicle;
use App\Models\VehicleType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleTest extends TestCase
{
    private function payload(array $o = []): array
    {
        return array_merge([
            'vehicle_type_id' => VehicleType::withoutGlobalScopes()->where('code', 'van')->value('id'),
            'registration_number' => '12345-a-6', 'make' => 'Renault', 'model' => 'Master', 'fuel_type' => 'diesel', 'capacity' => 1500,
        ], $o);
    }

    public function test_fleet_manager_can_create_and_list_vehicles(): void
    {
        Storage::fake('local');
        [$fm, $company] = $this->member('fleet_manager');
        Sanctum::actingAs($fm);

        $id = $this->post('/api/v1/vehicles', $this->payload(['image' => UploadedFile::fake()->image('van.jpg')]), ['Accept' => 'application/json'])
            ->assertCreated()
            ->assertJsonPath('data.registration_number', '12345-A-6')
            ->assertJsonPath('data.status', 'available')
            ->json('data.id');

        $this->getJson('/api/v1/vehicles?search=12345')->assertOk()->assertJsonPath('meta.pagination.total', 1);
        $this->get("/api/v1/files/vehicle-image/{$id}")->assertOk();
        $this->assertSame($company->id, Vehicle::withoutGlobalScopes()->find($id)->company_id);
    }

    public function test_registration_number_is_unique_per_company_only(): void
    {
        [$a, $companyA] = $this->member('fleet_manager');
        [$b] = $this->member('fleet_manager');
        $this->vehicle($companyA, ['registration_number' => 'AAA-1']);

        Sanctum::actingAs($a);
        $this->postJson('/api/v1/vehicles', $this->payload(['registration_number' => 'AAA-1']))->assertStatus(422)->assertJsonValidationErrors('registration_number');
        Sanctum::actingAs($b);
        $this->postJson('/api/v1/vehicles', $this->payload(['registration_number' => 'AAA-1']))->assertCreated();
    }

    public function test_user_cannot_access_another_companys_vehicle(): void
    {
        [$a] = $this->member('company_admin');
        [, $companyB] = $this->member('company_admin');
        $foreign = $this->vehicle($companyB);
        Sanctum::actingAs($a);

        $this->getJson("/api/v1/vehicles/{$foreign->id}")->assertNotFound();
        $this->putJson("/api/v1/vehicles/{$foreign->id}", ['make' => 'Hacked'])->assertNotFound();
        $this->deleteJson("/api/v1/vehicles/{$foreign->id}")->assertNotFound();
        $this->getJson("/api/v1/tracking/vehicles/{$foreign->id}/history")->assertNotFound();
        $this->assertSame(0, $this->getJson('/api/v1/vehicles')->json('meta.pagination.total'));
    }

    public function test_dispatcher_cannot_create_vehicles(): void
    {
        [$d] = $this->member('dispatcher');
        Sanctum::actingAs($d);
        $this->postJson('/api/v1/vehicles', $this->payload())->assertForbidden();
        $this->getJson('/api/v1/vehicles')->assertOk();
    }

    public function test_operational_statuses_cannot_be_set_manually(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        $v = $this->vehicle($company);
        Sanctum::actingAs($fm);

        $this->patchJson("/api/v1/vehicles/{$v->id}/status", ['status' => 'on_delivery'])->assertStatus(422);
        $this->patchJson("/api/v1/vehicles/{$v->id}/status", ['status' => 'out_of_service'])->assertOk()->assertJsonPath('data.status', 'out_of_service');
    }

    public function test_subscription_vehicle_limit_is_enforced(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        SubscriptionPlan::where('code', 'professional')->update(['max_vehicles' => 1]);
        $this->vehicle($company);
        Sanctum::actingAs($fm);

        $this->postJson('/api/v1/vehicles', $this->payload())->assertStatus(402)->assertJsonPath('code', 'plan_limit_reached');
    }

    public function test_vehicle_with_active_deliveries_cannot_be_archived(): void
    {
        [$admin, $company] = $this->member('company_admin');
        $v = $this->vehicle($company);
        $driver = $this->driver($company, [], true);
        $this->assigned($company, $this->delivery($company), $driver, $v);
        Sanctum::actingAs($admin);

        $this->deleteJson("/api/v1/vehicles/{$v->id}")->assertStatus(422)->assertJsonPath('code', 'vehicle_busy');
    }
}

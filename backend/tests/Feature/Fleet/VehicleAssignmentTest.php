<?php

namespace Tests\Feature\Fleet;

use App\Models\VehicleType;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VehicleAssignmentTest extends TestCase
{
    public function test_vehicle_assignment_history_and_exclusivity(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        $v = $this->vehicle($company);
        $d1 = $this->driver($company);
        $d2 = $this->driver($company);
        Sanctum::actingAs($fm);

        $this->postJson("/api/v1/vehicles/{$v->id}/assign", ['driver_id' => $d1->id])->assertCreated();
        $this->assertSame('assigned', $this->reload($v)->status->value);
        // Exclusive by default: second driver rejected.
        $this->postJson("/api/v1/vehicles/{$v->id}/assign", ['driver_id' => $d2->id])->assertStatus(422)->assertJsonPath('code', 'vehicle_assigned');

        $this->postJson("/api/v1/vehicles/{$v->id}/unassign")->assertOk();
        $this->postJson("/api/v1/vehicles/{$v->id}/assign", ['driver_id' => $d2->id])->assertCreated();

        $history = $this->getJson("/api/v1/vehicles/{$v->id}/history?tab=assignments")->assertOk()->json('data');
        $this->assertCount(2, $history);
        $this->assertNotNull(collect($history)->firstWhere('driver.id', $d1->id)['unassigned_at']);
    }

    public function test_licence_category_and_expiry_are_checked(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        $truck = $this->vehicle($company, ['vehicle_type_id' => VehicleType::withoutGlobalScopes()->where('code', 'heavy_truck')->value('id')]);
        $bDriver = $this->driver($company, ['license_type' => 'B']);
        $expired = $this->driver($company, ['license_type' => 'CE', 'license_expiry_date' => now()->subDay()->toDateString()]);
        Sanctum::actingAs($fm);

        $this->postJson("/api/v1/vehicles/{$truck->id}/assign", ['driver_id' => $bDriver->id])->assertStatus(422)->assertJsonPath('code', 'license_category');
        $this->postJson("/api/v1/vehicles/{$truck->id}/assign", ['driver_id' => $expired->id])->assertStatus(422)->assertJsonPath('code', 'license_expired');
    }

    public function test_cannot_assign_a_driver_from_another_company(): void
    {
        [$fm, $company] = $this->member('fleet_manager');
        [, $other] = $this->member('fleet_manager');
        $v = $this->vehicle($company);
        $foreignDriver = $this->driver($other);
        Sanctum::actingAs($fm);

        $this->postJson("/api/v1/vehicles/{$v->id}/assign", ['driver_id' => $foreignDriver->id])->assertStatus(422)->assertJsonValidationErrors('driver_id');
    }
}

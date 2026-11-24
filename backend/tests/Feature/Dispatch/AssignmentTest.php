<?php

namespace Tests\Feature\Dispatch;

use App\Models\DeliveryAssignment;
use App\Notifications\AssignmentNotification;
use App\Services\Maintenance\MaintenanceService;
use App\Services\Tenant\CompanySettings;
use Illuminate\Support\Facades\Notification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AssignmentTest extends TestCase
{
    public function test_dispatcher_assigns_and_driver_is_notified_then_accepts(): void
    {
        Notification::fake();
        [$dispatcher, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $d = $this->delivery($company);
        Sanctum::actingAs($dispatcher);

        $this->postJson('/api/v1/dispatch/assignments', ['delivery_id' => $d->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id])
            ->assertCreated()->assertJsonPath('data.status', 'pending');
        Notification::assertSentTo($driver->user, AssignmentNotification::class, fn ($n) => $n->action === 'assigned');
        $this->assertSame('assigned', $this->reload($d)->status->value);
        $this->assertSame('assigned', $this->reload($vehicle)->status->value);

        $assignmentId = DeliveryAssignment::withoutGlobalScopes()->value('id');
        Sanctum::actingAs($driver->user);
        $this->postJson("/api/v1/driver/assignments/{$assignmentId}/respond", ['accept' => true])->assertOk();
        $this->assertSame('accepted', $this->reload($d)->status->value);
    }

    public function test_rejection_needs_reason_and_company_policy(): void
    {
        [, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, [], true);
        $d = $this->assigned($company, $this->delivery($company), $driver, $this->vehicle($company), accept: false);
        $assignmentId = DeliveryAssignment::withoutGlobalScopes()->value('id');
        Sanctum::actingAs($driver->user);

        $this->postJson("/api/v1/driver/assignments/{$assignmentId}/respond", ['accept' => false])->assertStatus(422)->assertJsonPath('code', 'reason_required');
        app(CompanySettings::class)->update($company, ['drivers_can_reject_assignments' => false]);
        $this->postJson("/api/v1/driver/assignments/{$assignmentId}/respond", ['accept' => false, 'reason' => 'Too far'])->assertForbidden()->assertJsonPath('code', 'reject_not_allowed');
        app(CompanySettings::class)->update($company, ['drivers_can_reject_assignments' => true]);
        $this->postJson("/api/v1/driver/assignments/{$assignmentId}/respond", ['accept' => false, 'reason' => 'Too far'])->assertOk();
        $this->assertSame('pending', $this->reload($d)->status->value);
        $this->assertNull($this->reload($d)->assigned_driver_id);
    }

    public function test_assignment_rules_capacity_maintenance_licence(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, ['license_expiry_date' => now()->addHours(2)->toDateString()]);
        $okDriver = $this->driver($company);
        $small = $this->vehicle($company, ['capacity' => 10]);
        $big = $this->vehicle($company, ['capacity' => 1000]);
        $d = $this->delivery($company, ['scheduled_delivery_at' => now()->addDays(5)->toIso8601String()]); // 50 kg
        Sanctum::actingAs($dispatcher);

        $this->postJson("/api/v1/deliveries/{$d->id}/assign", ['driver_id' => $okDriver->id, 'vehicle_id' => $small->id])
            ->assertStatus(422)->assertJsonPath('code', 'assignment_invalid')->assertJsonFragment(['message' => 'Insufficient capacity: 50.00 kg needed, 10 kg free of 10 kg.']);
        $this->postJson("/api/v1/deliveries/{$d->id}/assign", ['driver_id' => $driver->id, 'vehicle_id' => $big->id])
            ->assertStatus(422)->assertJsonPath('message', "{$driver->fullName()}'s licence expires before the delivery date.");

        $this->within($company, fn () => app(MaintenanceService::class)->create([
            'vehicle_id' => $big->id, 'maintenance_type' => 'oil_change', 'title' => 'Oil', 'status' => 'in_progress',
        ], $company->owner));
        $this->assertSame('under_maintenance', $this->reload($big)->status->value);
        $this->postJson("/api/v1/deliveries/{$d->id}/assign", ['driver_id' => $okDriver->id, 'vehicle_id' => $big->id])
            ->assertStatus(422)->assertJsonPath('message', 'This vehicle is under maintenance.');
    }

    public function test_reassignment_preserves_history_and_conflicting_assignments_are_blocked(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $d1 = $this->driver($company, [], true);
        $d2 = $this->driver($company, [], true);
        $v1 = $this->vehicle($company);
        $v2 = $this->vehicle($company);
        $delivery = $this->assigned($company, $this->delivery($company), $d1, $v1);
        $other = $this->delivery($company);
        Sanctum::actingAs($dispatcher);

        // v1 carries d1's delivery: d2 cannot use it at the same time.
        $this->postJson("/api/v1/deliveries/{$other->id}/assign", ['driver_id' => $d2->id, 'vehicle_id' => $v1->id])->assertStatus(422);
        // d1 is on v1: cannot be given a delivery on v2 simultaneously.
        $this->postJson("/api/v1/deliveries/{$other->id}/assign", ['driver_id' => $d1->id, 'vehicle_id' => $v2->id])->assertStatus(422);

        $this->postJson("/api/v1/deliveries/{$delivery->id}/reassign", ['driver_id' => $d2->id, 'vehicle_id' => $v2->id])->assertOk()
            ->assertJsonPath('data.driver.id', $d2->id);
        $statuses = DeliveryAssignment::withoutGlobalScopes()->where('delivery_id', $delivery->id)->orderBy('id')->pluck('status')->map->value->all();
        $this->assertSame(['superseded', 'pending'], $statuses);
        $this->assertSame('available', $this->reload($v1)->status->value);
    }

    public function test_two_dispatchers_cannot_overbook_a_vehicle(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $vehicle = $this->vehicle($company, ['capacity' => 60]);
        $driver = $this->driver($company);
        $a = $this->delivery($company); // 50 kg
        $b = $this->delivery($company); // 50 kg
        Sanctum::actingAs($dispatcher);

        $this->postJson('/api/v1/dispatch/assignments', ['delivery_id' => $a->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id])->assertCreated();
        $this->postJson('/api/v1/dispatch/assignments', ['delivery_id' => $b->id, 'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id])->assertStatus(422);
    }

    public function test_unauthorized_users_cannot_assign(): void
    {
        [$accountant, $company] = $this->member('accountant');
        Sanctum::actingAs($accountant);
        $d = $this->delivery($company);

        $this->postJson("/api/v1/deliveries/{$d->id}/assign", ['driver_id' => 1, 'vehicle_id' => 1])->assertForbidden();
        $this->getJson('/api/v1/dispatch/board')->assertForbidden();
    }

    public function test_dispatch_board_groups_by_status(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $this->delivery($company);
        $this->assigned($company, $this->delivery($company), $this->driver($company, [], true), $this->vehicle($company));
        Sanctum::actingAs($dispatcher);

        $cols = collect($this->getJson('/api/v1/dispatch/board')->assertOk()->json('data.columns'))->keyBy('key');
        $this->assertSame(1, $cols['unassigned']['count']);
        $this->assertSame(1, $cols['accepted']['count']);
        $this->getJson('/api/v1/dispatch/available-drivers')->assertOk()->assertJsonCount(1, 'data');
    }
}

<?php

namespace Tests\Feature\Delivery;

use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DeliveryTest extends TestCase
{
    public function test_dispatcher_creates_a_delivery_with_computed_fields(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        Sanctum::actingAs($dispatcher);

        $res = $this->postJson('/api/v1/deliveries', $this->deliveryPayload([
            'items' => [['description' => 'Box', 'quantity' => 3, 'weight' => 2.5], ['description' => 'Crate', 'quantity' => 1, 'weight' => 10]],
            'delivery_fee' => '30.555', 'total_weight' => 1, 'status' => 'delivered', 'company_id' => 999,
        ]))->assertCreated();

        $res->assertJsonPath('data.delivery.status', 'pending')
            ->assertJsonPath('data.delivery.total_weight', '17.50')
            ->assertJsonPath('data.delivery.delivery_fee', '30.56')
            ->assertJsonPath('data.delivery.timeline.0.to', 'pending');
        $this->assertNotNull($res->json('data.delivery.distance_km'));
        $this->assertStringStartsWith('DEL-', $res->json('data.delivery.reference_number'));
        $this->assertSame($company->id, Delivery::withoutGlobalScopes()->first()->company_id);
    }

    public function test_invalid_status_transitions_are_rejected_and_every_change_is_recorded(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $d = $this->delivery($company);
        Sanctum::actingAs($dispatcher);

        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'in_transit'])->assertStatus(422)->assertJsonPath('code', 'invalid_transition');
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'assigned'])->assertStatus(422)->assertJsonPath('code', 'use_dedicated_endpoint');

        $driver = $this->driver($company, [], true);
        $this->assigned($company, $d, $driver, $this->vehicle($company));
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'picked_up'])->assertOk();
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'failed'])->assertStatus(422)->assertJsonPath('code', 'reason_required');
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'failed', 'notes' => 'Recipient absent'])->assertOk()->assertJsonPath('data.failure_reason', 'Recipient absent');

        $this->assertSame(['pending', 'assigned', 'accepted', 'picked_up', 'failed'], DeliveryStatusHistory::where('delivery_id', $d->id)->orderBy('id')->pluck('new_status')->all());
    }

    public function test_driver_cannot_update_another_drivers_delivery(): void
    {
        [, $company] = $this->member('company_admin');
        $mine = $this->driver($company, [], true);
        $other = $this->driver($company, [], true);
        $d = $this->assigned($company, $this->delivery($company), $other, $this->vehicle($company));
        Sanctum::actingAs($mine->user);

        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'picked_up'])->assertForbidden();
        $this->getJson("/api/v1/deliveries/{$d->id}")->assertForbidden();
        $this->getJson('/api/v1/deliveries')->assertOk()->assertJsonPath('meta.pagination.total', 0);
    }

    public function test_driver_lifecycle_requires_proof_otp_and_cod_confirmation(): void
    {
        Storage::fake('local');
        [, $company] = $this->member('company_admin');
        $driver = $this->driver($company, [], true);
        [$d, $otp] = $this->within($company, fn () => app(DeliveryService::class)->create($company, $this->deliveryPayload(['require_otp' => true, 'cod_amount' => '120.00']), null));
        $this->assigned($company, $d, $driver, $this->vehicle($company));
        Sanctum::actingAs($driver->user);

        foreach (['picked_up', 'in_transit', 'arrived'] as $s) {
            $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => $s])->assertOk();
        }
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'delivered'])->assertStatus(422)->assertJsonPath('code', 'proof_required');

        $this->post("/api/v1/deliveries/{$d->id}/proof", ['proof_type' => 'photo', 'photo' => UploadedFile::fake()->image('door.jpg')], ['Accept' => 'application/json'])->assertCreated();
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'delivered'])->assertStatus(422)->assertJsonPath('code', 'otp_required');

        $this->postJson("/api/v1/deliveries/{$d->id}/proof", ['proof_type' => 'otp', 'otp' => '000000'])->assertStatus(422)->assertJsonPath('code', 'otp_invalid');
        $this->postJson("/api/v1/deliveries/{$d->id}/proof", ['proof_type' => 'otp', 'otp' => $otp])->assertCreated();

        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'delivered'])->assertStatus(422)->assertJsonPath('code', 'cod_required');
        $this->patchJson("/api/v1/deliveries/{$d->id}/status", ['status' => 'delivered', 'cod_collected_amount' => 120])
            ->assertOk()->assertJsonPath('data.status', 'delivered')->assertJsonPath('data.cod_collected_amount', '120.00');

        $this->assertSame('available', Vehicle::withoutGlobalScopes()->find($this->reload($d)->assigned_vehicle_id)->status->value);
    }

    public function test_cancellation_rules(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $d = $this->assigned($company, $this->delivery($company), $driver, $vehicle);
        Sanctum::actingAs($dispatcher);

        $this->postJson("/api/v1/deliveries/{$d->id}/cancel", [])->assertStatus(422);
        $this->postJson("/api/v1/deliveries/{$d->id}/cancel", ['reason' => 'Customer request'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame('available', $this->reload($vehicle)->status->value);

        $picked = $this->assigned($company, $this->delivery($company), $driver, $vehicle);
        $this->patchJson("/api/v1/deliveries/{$picked->id}/status", ['status' => 'picked_up'])->assertOk();
        $this->postJson("/api/v1/deliveries/{$picked->id}/cancel", ['reason' => 'x'])->assertStatus(422)->assertJsonPath('code', 'cancel_not_allowed');
    }

    public function test_user_cannot_access_another_companys_delivery(): void
    {
        [$a] = $this->member('dispatcher');
        [, $other] = $this->member('dispatcher');
        $foreign = $this->delivery($other);
        Sanctum::actingAs($a);

        $this->getJson("/api/v1/deliveries/{$foreign->id}")->assertNotFound();
        $this->patchJson("/api/v1/deliveries/{$foreign->id}/status", ['status' => 'cancelled'])->assertNotFound();
        $this->getJson("/api/v1/tracking/deliveries/{$foreign->id}")->assertNotFound();
    }

    public function test_export_csv_and_xlsx(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $this->delivery($company);
        Sanctum::actingAs($dispatcher);

        $csv = $this->get('/api/v1/deliveries/export?format=csv')->assertOk();
        $this->assertStringContainsString('Reference', $csv->streamedContent());
        $xlsx = $this->get('/api/v1/deliveries/export?format=xlsx')->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
    }
}

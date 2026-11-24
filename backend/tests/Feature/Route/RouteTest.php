<?php

namespace Tests\Feature\Route;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\RouteOptimizationLog;
use App\Models\SubscriptionPlan;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Maps\MapboxProvider;
use App\Services\Maps\MapsProvider;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RouteTest extends TestCase
{
    public function test_route_with_stops_uses_straight_line_when_no_provider_and_refuses_optimisation(): void
    {
        config(['fleetflow.maps.provider' => 'none']);
        [$dispatcher, $company] = $this->member('dispatcher');
        $a = $this->delivery($company);
        $b = $this->delivery($company, ['delivery_latitude' => 33.9716, 'delivery_longitude' => -6.8498]);
        Sanctum::actingAs($dispatcher);

        $route = $this->postJson('/api/v1/routes', [
            'name' => 'Rabat run', 'route_date' => now()->toDateString(),
            'start_latitude' => 33.5731, 'start_longitude' => -7.5898, 'delivery_ids' => [$a->id, $b->id],
        ])->assertCreated()->assertJsonPath('data.distance_source', 'straight_line')->assertJsonPath('data.estimated_duration', null)->json('data');

        $this->assertCount(2, $route['stops']);
        $this->assertGreaterThan(80, (float) $route['planned_distance']);

        // No fake optimisation: clear 503 when no routing provider is configured.
        $this->postJson("/api/v1/routes/{$route['id']}/optimize")->assertStatus(503)->assertJsonPath('code', 'maps_provider_unavailable');

        // Reorder stops
        $ids = array_reverse(array_column($route['stops'], 'id'));
        $this->putJson("/api/v1/routes/{$route['id']}", ['stop_order' => $ids])->assertOk()->assertJsonPath('data.stops.0.id', $ids[0]);
    }

    public function test_optimisation_with_a_real_provider_integration(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $this->app->instance(MapsProvider::class, new MapboxProvider('test-token'));
        Http::fake([
            'api.mapbox.com/optimized-trips/*' => Http::response([
                'code' => 'Ok',
                'trips' => [['distance' => 90000, 'duration' => 5400, 'geometry' => ['type' => 'LineString', 'coordinates' => []]]],
                'waypoints' => [['waypoint_index' => 0], ['waypoint_index' => 2], ['waypoint_index' => 1]],
            ]),
            'api.mapbox.com/directions/*' => Http::response([
                'routes' => [['distance' => 90000, 'duration' => 5400, 'geometry' => ['type' => 'LineString', 'coordinates' => []], 'legs' => [['distance' => 40000, 'duration' => 2400], ['distance' => 50000, 'duration' => 3000]]]],
            ]),
        ]);
        $a = $this->delivery($company);
        $b = $this->delivery($company, ['delivery_latitude' => 33.9716, 'delivery_longitude' => -6.8498]);
        Sanctum::actingAs($dispatcher);

        $route = $this->postJson('/api/v1/routes', ['name' => 'Opt', 'route_date' => now()->toDateString(), 'start_latitude' => 33.57, 'start_longitude' => -7.58, 'delivery_ids' => [$a->id, $b->id]])
            ->assertCreated()->assertJsonPath('data.distance_source', 'mapbox')->assertJsonPath('data.estimated_duration', 90)->json('data');

        $this->postJson("/api/v1/routes/{$route['id']}/optimize")->assertOk()
            ->assertJsonPath('data.stops.0.delivery.id', $b->id)
            ->assertJsonPath('data.optimizations.0.provider', 'mapbox');
        $this->assertSame(1, RouteOptimizationLog::count());
    }

    public function test_route_lifecycle_and_feature_gate(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $driver = $this->driver($company, [], true);
        $vehicle = $this->vehicle($company);
        $d = $this->assigned($company, $this->delivery($company), $driver, $vehicle);
        Sanctum::actingAs($dispatcher);

        $id = $this->postJson('/api/v1/routes', ['name' => 'Day', 'route_date' => now()->toDateString(), 'delivery_ids' => [$d->id]])->json('data.id');
        $this->postJson("/api/v1/routes/{$id}/start")->assertStatus(422)->assertJsonPath('code', 'route_unassigned');
        $this->putJson("/api/v1/routes/{$id}", ['driver_id' => $driver->id, 'vehicle_id' => $vehicle->id])->assertOk();
        $this->postJson("/api/v1/routes/{$id}/start")->assertOk()->assertJsonPath('data.status', 'in_progress');
        $this->postJson("/api/v1/routes/{$id}/complete")->assertStatus(422)->assertJsonPath('code', 'stops_open');

        $this->within($company, fn () => app(DeliveryStatusService::class)->transition(Delivery::find($d->id), DeliveryStatus::PickedUp, null));
        $this->within($company, fn () => app(DeliveryStatusService::class)->transition(Delivery::find($d->id), DeliveryStatus::Failed, null, 'Closed'));
        $this->postJson("/api/v1/routes/{$id}/complete")->assertOk()->assertJsonPath('data.status', 'completed');

        // Starter plan has no route planning.
        $company->currentSubscription->forceFill(['subscription_plan_id' => SubscriptionPlan::where('code', 'starter')->value('id')])->save();
        $this->postJson('/api/v1/routes', ['name' => 'X', 'route_date' => now()->toDateString()])->assertStatus(402)->assertJsonPath('code', 'plan_feature_unavailable');
    }
}

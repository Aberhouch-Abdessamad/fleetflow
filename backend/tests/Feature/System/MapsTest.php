<?php

namespace Tests\Feature\System;

use App\Services\Maps\MapboxProvider;
use App\Services\Maps\MapsProvider;
use App\Services\Maps\MapsUnavailableException;
use App\Services\Maps\OsmMapsProvider;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MapsTest extends TestCase
{
    public function test_default_provider_geocodes_without_an_api_key(): void
    {
        [$user, $company] = $this->member('dispatcher');
        Sanctum::actingAs($user);

        $this->getJson('/api/v1/maps/config')->assertOk()->assertJson([
            'data' => ['provider' => 'osm', 'geocoding' => true, 'routing' => true, 'optimization' => true],
        ]);

        Http::fake([
            'nominatim.openstreetmap.org/search*' => Http::response([
                ['display_name' => '12 Rue Atlas, Rabat, Morocco', 'lat' => '34.0209', 'lon' => '-6.8416', 'address' => ['city' => 'Rabat']],
                ['display_name' => 'Atlas, Casablanca, Morocco', 'lat' => '33.5731', 'lon' => '-7.5898', 'address' => ['city' => 'Casablanca']],
            ]),
        ]);

        $this->getJson('/api/v1/maps/geocode?q=Rue%20Atlas')->assertOk()->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.latitude', 34.0209)
            ->assertJsonPath('data.0.city', 'Rabat');
        Http::assertSentCount(1); // search, cached on repeat
        $this->getJson('/api/v1/maps/geocode?q=Rue%20Atlas')->assertOk();
        Http::assertSentCount(1);
    }

    public function test_reverse_geocode_maps_a_pin_to_an_address_or_nothing(): void
    {
        [$user] = $this->member('dispatcher');
        Sanctum::actingAs($user);
        Http::fake([
            'nominatim.openstreetmap.org/reverse*' => Http::sequence()
                ->push(['display_name' => 'Somewhere, Rabat', 'address' => ['city' => 'Rabat']])
                ->push(['error' => 'Unable to geocode'], 404),
        ]);

        $this->getJson('/api/v1/maps/reverse?lat=34.02&lng=-6.84')->assertOk()->assertJsonPath('data.label', 'Somewhere, Rabat');
        $this->getJson('/api/v1/maps/reverse?lat=0&lng=0')->assertOk()->assertJsonPath('data', null);
    }

    public function test_osm_provider_routes_and_optimises_through_the_key_less_osrm_endpoint(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $this->app->instance(MapsProvider::class, new OsmMapsProvider('http://osrm.test'));
        Http::fake([
            'osrm.test/route/*' => Http::response([
                'code' => 'Ok',
                'routes' => [[
                    'distance' => 90000, 'duration' => 5400, 'geometry' => ['type' => 'LineString', 'coordinates' => []],
                    'legs' => [['distance' => 40000, 'duration' => 2400], ['distance' => 50000, 'duration' => 3000]],
                ]],
            ]),
        ]);
        $a = $this->delivery($company);
        $b = $this->delivery($company, ['delivery_latitude' => 33.9716, 'delivery_longitude' => -6.8498]);
        Sanctum::actingAs($dispatcher);

        $route = $this->postJson('/api/v1/routes', ['name' => 'Casablanca loop', 'route_date' => now()->toDateString(), 'start_latitude' => 33.57, 'start_longitude' => -7.58, 'delivery_ids' => [$a->id, $b->id]])
            ->assertCreated()
            ->assertJsonPath('data.distance_source', 'osm')
            ->assertJsonPath('data.estimated_duration', 90)
            ->json('data');
        $this->assertGreaterThan(80, (float) $route['planned_distance']);
    }

    public function test_osm_provider_falls_back_to_straight_line_when_osrm_is_unreachable(): void
    {
        [$dispatcher, $company] = $this->member('dispatcher');
        $this->app->instance(MapsProvider::class, new OsmMapsProvider('http://osrm-unreachable.test'));
        Http::fake(['osrm-unreachable.test/*' => Http::response(null, 503)]);
        Sanctum::actingAs($dispatcher);

        $this->postJson('/api/v1/routes', [
            'name' => 'Rabat run', 'route_date' => now()->toDateString(),
            'start_latitude' => 33.5731, 'start_longitude' => -7.5898, 'delivery_ids' => [$this->delivery($company, ['delivery_latitude' => 33.9716, 'delivery_longitude' => -6.8498])->id],
        ])->assertCreated()->assertJsonPath('data.distance_source', 'straight_line')->assertJsonPath('data.estimated_duration', null);
    }

    public function test_osm_provider_without_an_osrm_endpoint_only_geocodes(): void
    {
        $provider = new OsmMapsProvider('');
        $this->assertTrue($provider->available());
        $this->assertFalse($provider->routingAvailable());
        $this->expectException(MapsUnavailableException::class);
        $provider->route([[34.0, -6.8], [33.5, -7.5]]);
    }

    public function test_routing_available_flag_follows_the_provider(): void
    {
        config(['fleetflow.maps.provider' => 'mapbox']);
        $this->app->instance(MapsProvider::class, new MapboxProvider('key'));
        $user = $this->member('dispatcher')[0];
        Sanctum::actingAs($user);
        $this->getJson('/api/v1/maps/config')->assertOk()
            ->assertJsonPath('data.provider', 'mapbox')
            ->assertJsonPath('data.routing', true);
    }
}

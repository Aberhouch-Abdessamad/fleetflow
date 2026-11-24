<?php

namespace App\Services\Maps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/** Mapbox Geocoding v5, Directions v5 and Optimization v1 APIs. */
class MapboxProvider implements MapsProvider
{
    public function __construct(private readonly string $token) {}

    public function name(): string
    {
        return 'mapbox';
    }

    public function available(): bool
    {
        return $this->token !== '';
    }

    public function routingAvailable(): bool
    {
        return $this->available();
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://api.mapbox.com')->timeout(15)->retry(2, 300, throw: false);
    }

    public function geocode(string $query, ?string $country = null): array
    {
        $res = $this->http()->get('/geocoding/v5/mapbox.places/'.rawurlencode($query).'.json', array_filter([
            'access_token' => $this->token, 'limit' => 5, 'country' => $country ? strtolower($country) : null,
        ]));
        $this->ensure($res);

        return collect($res->json('features', []))->map(fn ($f) => [
            'label' => $f['place_name'],
            'latitude' => (float) $f['center'][1],
            'longitude' => (float) $f['center'][0],
            'city' => collect($f['context'] ?? [])->first(fn ($c) => str_starts_with($c['id'], 'place.'))['text'] ?? null,
        ])->all();
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $res = $this->http()->get("/geocoding/v5/mapbox.places/{$lng},{$lat}.json", ['access_token' => $this->token, 'limit' => 1]);
        $this->ensure($res);
        $f = $res->json('features.0');

        return $f ? ['label' => $f['place_name'], 'latitude' => $lat, 'longitude' => $lng, 'city' => null] : null;
    }

    public function route(array $points): array
    {
        $coords = implode(';', array_map(fn ($p) => "{$p[1]},{$p[0]}", $points));
        $res = $this->http()->get("/directions/v5/mapbox/driving/{$coords}", [
            'access_token' => $this->token, 'geometries' => 'geojson', 'overview' => 'full',
        ]);
        $this->ensure($res);
        $route = $res->json('routes.0') ?? throw new MapsUnavailableException('Mapbox returned no route for these stops.');

        return [
            'distance_km' => round($route['distance'] / 1000, 2),
            'duration_minutes' => (int) round($route['duration'] / 60),
            'geometry' => $route['geometry'],
            'legs' => array_map(fn ($l) => ['distance_km' => round($l['distance'] / 1000, 2), 'duration_minutes' => (int) round($l['duration'] / 60)], $route['legs']),
        ];
    }

    public function optimize(array $points, bool $fixedEnd = false): array
    {
        if (count($points) > 12) {
            throw new MapsUnavailableException('Mapbox optimisation supports up to 12 points per request.');
        }
        $coords = implode(';', array_map(fn ($p) => "{$p[1]},{$p[0]}", $points));
        $res = $this->http()->get("/optimized-trips/v1/mapbox/driving/{$coords}", [
            'access_token' => $this->token, 'source' => 'first', 'destination' => $fixedEnd ? 'last' : 'any',
            'roundtrip' => 'false', 'geometries' => 'geojson', 'overview' => 'full',
        ]);
        $this->ensure($res);
        $trip = $res->json('trips.0') ?? throw new MapsUnavailableException('Mapbox could not optimise these stops.');
        // waypoints[i].waypoint_index = position of input i in the optimised trip
        $order = collect($res->json('waypoints'))->map(fn ($w, $i) => ['in' => $i, 'pos' => $w['waypoint_index']])
            ->sortBy('pos')->pluck('in')->values()->all();

        return [
            'order' => $order,
            'distance_km' => round($trip['distance'] / 1000, 2),
            'duration_minutes' => (int) round($trip['duration'] / 60),
            'geometry' => $trip['geometry'],
        ];
    }

    private function ensure($res): void
    {
        if (! $res->successful()) {
            throw new MapsUnavailableException('Mapbox request failed ('.$res->status().'): '.($res->json('message') ?? 'unknown error'));
        }
    }
}

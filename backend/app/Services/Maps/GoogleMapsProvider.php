<?php

namespace App\Services\Maps;

use Illuminate\Support\Facades\Http;

/** Google Geocoding + Directions API (waypoint optimisation via optimize:true). */
class GoogleMapsProvider implements MapsProvider
{
    public function __construct(private readonly string $key) {}

    public function name(): string
    {
        return 'google';
    }

    public function available(): bool
    {
        return $this->key !== '';
    }

    public function routingAvailable(): bool
    {
        return $this->available();
    }

    public function geocode(string $query, ?string $country = null): array
    {
        $res = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/geocode/json', array_filter([
            'address' => $query, 'key' => $this->key, 'components' => $country ? 'country:'.$country : null,
        ]));
        $this->ensure($res);

        return collect($res->json('results', []))->take(5)->map(fn ($r) => [
            'label' => $r['formatted_address'],
            'latitude' => (float) $r['geometry']['location']['lat'],
            'longitude' => (float) $r['geometry']['location']['lng'],
            'city' => collect($r['address_components'])->first(fn ($c) => in_array('locality', $c['types'], true))['long_name'] ?? null,
        ])->all();
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $res = Http::timeout(15)->get('https://maps.googleapis.com/maps/api/geocode/json', ['latlng' => "{$lat},{$lng}", 'key' => $this->key]);
        $this->ensure($res);
        $r = $res->json('results.0');

        return $r ? ['label' => $r['formatted_address'], 'latitude' => $lat, 'longitude' => $lng, 'city' => null] : null;
    }

    public function route(array $points): array
    {
        return $this->directions($points, false);
    }

    public function optimize(array $points, bool $fixedEnd = false): array
    {
        // Google keeps origin & destination fixed and optimises the waypoints in between.
        $r = $this->directions($points, true);

        return ['order' => $r['order'], 'distance_km' => $r['distance_km'], 'duration_minutes' => $r['duration_minutes'], 'geometry' => $r['geometry']];
    }

    private function directions(array $points, bool $optimize): array
    {
        $origin = array_shift($points);
        $dest = array_pop($points) ?? $origin;
        $via = array_map(fn ($p) => "{$p[0]},{$p[1]}", $points);
        $res = Http::timeout(20)->get('https://maps.googleapis.com/maps/api/directions/json', array_filter([
            'origin' => "{$origin[0]},{$origin[1]}",
            'destination' => "{$dest[0]},{$dest[1]}",
            'waypoints' => $via ? ($optimize ? 'optimize:true|' : '').implode('|', $via) : null,
            'key' => $this->key,
        ]));
        $this->ensure($res);
        $route = $res->json('routes.0') ?? throw new MapsUnavailableException('Google returned no route for these stops.');
        $legs = $route['legs'];
        $wp = $route['waypoint_order'] ?? range(0, count($via) - 1);

        return [
            'distance_km' => round(array_sum(array_column(array_column($legs, 'distance'), 'value')) / 1000, 2),
            'duration_minutes' => (int) round(array_sum(array_column(array_column($legs, 'duration'), 'value')) / 60),
            'geometry' => ['type' => 'encoded_polyline', 'value' => $route['overview_polyline']['points'] ?? null],
            'legs' => array_map(fn ($l) => ['distance_km' => round($l['distance']['value'] / 1000, 2), 'duration_minutes' => (int) round($l['duration']['value'] / 60)], $legs),
            'order' => array_merge([0], array_map(fn ($i) => $i + 1, $wp), [count($via) + 1]),
        ];
    }

    private function ensure($res): void
    {
        if (! $res->successful() || ! in_array($res->json('status'), ['OK', 'ZERO_RESULTS'], true)) {
            throw new MapsUnavailableException('Google Maps request failed: '.($res->json('error_message') ?? $res->json('status') ?? $res->status()));
        }
    }
}

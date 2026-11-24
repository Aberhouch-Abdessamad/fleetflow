<?php

namespace App\Services\Maps;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

/**
 * Key-less OpenStreetMap provider: Nominatim for place search/reverse geocoding
 * and OSRM for road routing + stop optimisation. Needs no API key; fair-use
 * policies ask requests to identify themselves and be cached / rate-limited —
 * the controller caches geocoding for 24 h and the endpoint is throttled, and
 * OSRM may be pointed at a self-hosted instance via OSRM_URL.
 */
class OsmMapsProvider implements MapsProvider
{
    public function __construct(private readonly string $osrmBaseUrl = self::DEFAULT_OSRM) {}

    /** Default public OSRM demo server — key-less, best-effort. */
    public const DEFAULT_OSRM = 'https://router.project-osrm.org';

    public function name(): string
    {
        return 'osm';
    }

    public function available(): bool
    {
        return true;
    }

    public function routingAvailable(): bool
    {
        // OSRM key-less routing only works when an endpoint is configured.
        return trim($this->osrmBaseUrl) !== '';
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl('https://nominatim.openstreetmap.org')
            ->withHeaders([
                // Nominatim rejects requests without an identifying User-Agent.
                'User-Agent' => 'FleetFlow/1.0 (self-hosted fleet management)',
                'Accept-Language' => 'en',
            ])
            ->timeout(15);
    }

    public function geocode(string $query, ?string $country = null): array
    {
        $res = $this->http()->get('/search', array_filter([
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 5,
            'countrycodes' => $country ? strtolower($country) : null,
        ]));
        $this->ensure($res);

        return collect($res->json() ?? [])
            ->filter(fn ($r) => is_array($r) && filled($r['display_name'] ?? null))
            ->values()
            ->map(fn ($r) => [
                'label' => (string) $r['display_name'],
                'latitude' => (float) $r['lat'],
                'longitude' => (float) $r['lon'],
                'city' => $this->city($r['address'] ?? []),
            ])->all();
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        $res = $this->http()->get('/reverse', ['lat' => $lat, 'lon' => $lng, 'format' => 'jsonv2', 'zoom' => 18]);
        // Open water / no match: Nominatim answers 404 or {"error": "Unable to geocode"}.
        if (! $res->successful() || $res->json('error')) {
            return null;
        }
        $label = (string) $res->json('display_name');

        return $label === '' ? null : [
            'label' => $label,
            'latitude' => $lat, 'longitude' => $lng,
            'city' => $this->city($res->json('address') ?? []),
        ];
    }

    public function route(array $points): array
    {
        if (! $this->routingAvailable()) {
            throw new MapsUnavailableException('No routing endpoint configured. Set OSRM_URL, or MAPS_PROVIDER=mapbox/google for road routing.');
        }
        $res = $this->osrm()->get('/route/v1/driving/'.implode(';', $this->coords($points)), [
            'overview' => 'full', 'geometries' => 'geojson', 'steps' => 'false',
        ]);
        $this->ensure($res);
        $route = $res->json('routes.0') ?? throw new MapsUnavailableException('OSRM returned no route for these stops.');

        return [
            'distance_km' => round($route['distance'] / 1000, 2),
            'duration_minutes' => (int) round($route['duration'] / 60),
            'geometry' => $route['geometry'] ?? null,
            'legs' => array_map(fn ($l) => ['distance_km' => round($l['distance'] / 1000, 2), 'duration_minutes' => (int) round($l['duration'] / 60)], $route['legs'] ?? []),
        ];
    }

    public function optimize(array $points, bool $fixedEnd = false): array
    {
        if (! $this->routingAvailable()) {
            throw new MapsUnavailableException('No routing endpoint configured. Set OSRM_URL, or MAPS_PROVIDER=mapbox/google for stop optimisation.');
        }
        if (count($points) < 2) {
            throw new MapsUnavailableException('Optimisation needs at least two points.');
        }
        if (count($points) > 12) {
            throw new MapsUnavailableException('Optimisation supports up to 12 points per request.');
        }
        $res = $this->osrm()->get('/trip/v1/driving/'.implode(';', $this->coords($points)), array_filter([
            'source' => 'first', 'roundtrip' => 'false', 'geometries' => 'geojson', 'overview' => 'full',
            // OSRM trip: fix the last stop only when the end point is a real depot.
            'destination' => $fixedEnd ? 'last' : null,
        ]));
        $this->ensure($res);
        $trip = $res->json('trips.0') ?? throw new MapsUnavailableException('OSRM could not optimise these stops.');
        // waypoints[i].trip_index = position of input i in the optimised trip
        $order = collect($res->json('waypoints'))->map(fn ($w, $i) => ['in' => $i, 'pos' => $w['trip_index']])
            ->sortBy('pos')->pluck('in')->values()->all();

        return [
            'order' => $order,
            'distance_km' => round($trip['distance'] / 1000, 2),
            'duration_minutes' => (int) round($trip['duration'] / 60),
            'geometry' => $trip['geometry'] ?? null,
        ];
    }

    /** OSRM wants [lng,lat]; the app works with [lat,lng]. */
    private function coords(array $points): array
    {
        return array_map(fn ($p) => "{$p[1]},{$p[0]}", $points);
    }

    private function osrm(): PendingRequest
    {
        return Http::baseUrl(rtrim($this->osrmBaseUrl, '/'))->timeout(20);
    }

    /** @return ?string the most specific settlement from a Nominatim address block */
    private function city(array $address): ?string
    {
        return collect($address)->only(['city', 'town', 'village', 'municipality', 'county'])->filter()->first();
    }

    private function ensure($res): void
    {
        if (! $res->successful()) {
            throw new MapsUnavailableException('OpenStreetMap request failed ('.$res->status().'): '.($res->json('error') ?? 'unknown error'));
        }
    }
}

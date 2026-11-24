<?php

namespace App\Services\Maps;

/**
 * Provider abstraction for geocoding, routing and stop-sequence optimisation.
 * Business logic depends only on this interface; swap Mapbox ↔ Google ↔ OpenStreetMap.
 *
 * `available()` reports whether a place-search (geocoding) provider is usable.
 * A provider may offer geocoding without routing (eg. the key-less OpenStreetMap
 * provider), so routing availability is reported separately.
 */
interface MapsProvider
{
    public function name(): string;

    public function available(): bool;

    /** Whether the provider can compute road distances/durations (and optimisation). */
    public function routingAvailable(): bool;

    /** @return list<array{label:string,latitude:float,longitude:float,city:?string}> */
    public function geocode(string $query, ?string $country = null): array;

    public function reverseGeocode(float $lat, float $lng): ?array;

    /**
     * Route through points in the given order.
     *
     * @param  list<array{0:float,1:float}>  $points  [lat,lng]
     * @return array{distance_km:float,duration_minutes:int,geometry:?array,legs:list<array{distance_km:float,duration_minutes:int}>}
     */
    public function route(array $points): array;

    /**
     * Optimise the order of intermediate stops. First point = start; if $roundTrip
     * false and $fixedEnd true the last point stays last.
     *
     * @param  list<array{0:float,1:float}>  $points
     * @return array{order:list<int>,distance_km:float,duration_minutes:int,geometry:?array}
     */
    public function optimize(array $points, bool $fixedEnd = false): array;
}

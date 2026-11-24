<?php

namespace App\Services\Maps;

/** Used when no provider is configured. Never fabricates results — every call fails clearly. */
class NullMapsProvider implements MapsProvider
{
    public function name(): string
    {
        return 'none';
    }

    public function available(): bool
    {
        return false;
    }

    public function routingAvailable(): bool
    {
        return false;
    }

    public function geocode(string $query, ?string $country = null): array
    {
        throw new MapsUnavailableException;
    }

    public function reverseGeocode(float $lat, float $lng): ?array
    {
        throw new MapsUnavailableException;
    }

    public function route(array $points): array
    {
        throw new MapsUnavailableException;
    }

    public function optimize(array $points, bool $fixedEnd = false): array
    {
        throw new MapsUnavailableException;
    }
}

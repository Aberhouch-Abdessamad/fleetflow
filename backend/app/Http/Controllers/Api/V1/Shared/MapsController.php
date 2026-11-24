<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Services\Maps\MapsProvider;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class MapsController extends Controller
{
    public function __construct(private readonly MapsProvider $maps) {}

    public function config(): JsonResponse
    {
        return ApiResponse::ok([
            'provider' => $this->maps->available() ? $this->maps->name() : null,
            'geocoding' => $this->maps->available(),
            'routing' => $this->maps->routingAvailable(),
            'optimization' => $this->maps->routingAvailable(),
        ]);
    }

    public function geocode(Request $request): JsonResponse
    {
        $data = $request->validate(['q' => ['required', 'string', 'min:3', 'max:200']]);
        $country = app(TenantContext::class)->company()?->country;
        $results = Cache::remember('geo:'.md5(strtolower($data['q']).$country), 86400, fn () => $this->maps->geocode($data['q'], $country));

        return ApiResponse::ok($results);
    }

    public function reverse(Request $request): JsonResponse
    {
        $data = $request->validate(['lat' => ['required', 'numeric', 'between:-90,90'], 'lng' => ['required', 'numeric', 'between:-180,180']]);

        return ApiResponse::ok($this->maps->reverseGeocode((float) $data['lat'], (float) $data['lng']));
    }
}

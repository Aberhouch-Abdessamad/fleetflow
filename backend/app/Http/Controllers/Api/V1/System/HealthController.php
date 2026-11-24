<?php

namespace App\Http\Controllers\Api\V1\System;

use App\Http\Controllers\Controller;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Real round-trip check used by the SPA status page and uptime monitors. */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $started = microtime(true);
        try {
            DB::select('select 1');
            $db = [
                'ok' => true,
                'driver' => DB::connection()->getDriverName(),
                'version' => DB::connection()->getDriverName() === 'sqlite'
                    ? DB::selectOne('select sqlite_version() as v')->v
                    : DB::selectOne('select version() as v')->v,
                'latency_ms' => round((microtime(true) - $started) * 1000, 1),
            ];
        } catch (Throwable) {
            $db = ['ok' => false, 'driver' => config('database.default')];
        }

        return ApiResponse::ok([
            'app' => config('app.name'),
            'api_version' => 'v1',
            'environment' => app()->environment(),
            'time' => now()->toIso8601String(),
            'database' => $db,
            'maps_provider' => config('fleetflow.maps.provider') ?: null,
            'broadcasting' => config('broadcasting.default', 'log'),
        ], status: $db['ok'] ? 200 : 503);
    }
}

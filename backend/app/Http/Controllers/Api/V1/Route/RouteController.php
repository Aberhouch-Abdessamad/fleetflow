<?php

namespace App\Http\Controllers\Api\V1\Route;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Route\RouteRequest;
use App\Http\Resources\RouteResource;
use App\Models\Delivery;
use App\Models\Route;
use App\Services\Route\RouteService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RouteController extends Controller
{
    private const DETAIL = ['stops.delivery', 'driver', 'vehicle', 'optimizationLogs'];

    public function __construct(private readonly RouteService $routes) {}

    public function index(Request $request): JsonResponse
    {
        $q = Route::with(['driver', 'vehicle'])->withCount('stops')
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('date'), fn ($q, $d) => $q->whereDate('route_date', $d))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('driver_id', $d))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', $this->like($s))->orWhere('reference_number', 'like', $this->like($s))));

        return $this->paginated($this->sort($q, $request, ['route_date', 'name', 'created_at'], '-route_date'), RouteResource::class, $request);
    }

    public function store(RouteRequest $request): JsonResponse
    {
        return ApiResponse::created(new RouteResource($this->routes->create($request->validated(), $request->user())->load(self::DETAIL)), 'Route created.');
    }

    public function show(Route $route): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($route->load(self::DETAIL)));
    }

    public function update(RouteRequest $request, Route $route): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($this->routes->update($route, $request->validated())->load(self::DETAIL)), 'Route updated.');
    }

    public function addStops(Request $request, Route $route): JsonResponse
    {
        $data = $request->validate([
            'delivery_ids' => ['required', 'array', 'min:1', 'max:100'],
            'delivery_ids.*' => ['integer', Rule::exists('deliveries', 'id')->where('company_id', app(TenantContext::class)->id())],
        ]);
        $this->routes->addStops($route, $data['delivery_ids']);

        return ApiResponse::ok(new RouteResource($this->routes->recalculate($route)->load(self::DETAIL)), 'Stops added.');
    }

    public function removeStop(Route $route, int $stop): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($this->routes->removeStop($route, $stop)->load(self::DETAIL)), 'Stop removed.');
    }

    public function optimize(Request $request, Route $route): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($this->routes->optimize($route, $request->user())->load(self::DETAIL)), 'Route optimised.');
    }

    public function start(Route $route): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($this->routes->start($route)->load(self::DETAIL)), 'Route started.');
    }

    public function complete(Route $route): JsonResponse
    {
        return ApiResponse::ok(new RouteResource($this->routes->complete($route)->load(self::DETAIL)), 'Route completed.');
    }

    public function destroy(Route $route): JsonResponse
    {
        abort_if($route->status->value === 'in_progress', 422, 'Routes in progress cannot be deleted.');
        Delivery::where('route_id', $route->id)->update(['route_id' => null]);
        $route->delete();

        return ApiResponse::ok(null, 'Route deleted.');
    }
}

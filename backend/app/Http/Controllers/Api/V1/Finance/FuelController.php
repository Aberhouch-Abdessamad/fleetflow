<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\FuelRequest;
use App\Http\Resources\FuelRecordResource;
use App\Models\Driver;
use App\Models\FuelRecord;
use App\Services\Fuel\FuelService;
use App\Support\ApiResponse;
use App\Support\Money;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FuelController extends Controller
{
    public function __construct(private readonly FuelService $service) {}

    private function scoped(Request $request)
    {
        $user = $request->user();
        $q = FuelRecord::with(['vehicle', 'driver']);
        if (! $user->can('fuel.view')) {
            if (! $user->can('fuel.create_own')) {
                throw new AuthorizationException;
            }
            $q->where('created_by', $user->id);
        }

        return $q->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('driver_id', $d))
            ->when($request->query('from'), fn ($q, $d) => $q->where('recorded_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('recorded_at', '<=', $d.' 23:59:59'));
    }

    public function index(Request $request): JsonResponse
    {
        $q = $this->scoped($request);
        $all = (clone $q)->get(['vehicle_id', 'quantity', 'total_cost', 'odometer', 'full_tank', 'recorded_at']);

        $response = $this->paginated($this->sort($q, $request, ['recorded_at', 'total_cost', 'quantity'], '-recorded_at'), FuelRecordResource::class, $request);
        $payload = $response->getData(true);
        $eff = $all->groupBy('vehicle_id')->map(fn ($r) => $this->service->efficiency($r));
        $km = $eff->sum('distance_km');
        $litresSeg = $eff->sum('litres');
        $payload['meta']['summary'] = [
            'records' => $all->count(),
            'litres' => round((float) $all->sum('quantity'), 2),
            'total_cost' => Money::add(...$all->pluck('total_cost')->map(fn ($x) => (string) $x)->all() ?: ['0']),
            'average_unit_price' => $all->sum('quantity') > 0 ? round(Money::toCents(Money::add(...$all->pluck('total_cost')->map(fn ($x) => (string) $x)->all())) / 100 / $all->sum('quantity'), 3) : null,
            'l_per_100km' => $km > 0 ? round($litresSeg / $km * 100, 2) : null,
            'measured_distance_km' => $km ?: null,
        ];

        return response()->json($payload);
    }

    public function store(FuelRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = collect($request->validated())->except('receipt')->all();
        if (! $user->can('fuel.manage')) {
            // Drivers record fuel only for themselves and their current vehicle.
            $driver = Driver::where('user_id', $user->id)->first();
            if (! $user->can('fuel.create_own') || ! $driver || $driver->activeVehicleAssignment?->vehicle_id !== (int) $data['vehicle_id']) {
                throw new AuthorizationException;
            }
            $data['driver_id'] = $driver->id;
        }
        $r = $this->service->create($data, $user, $request->file('receipt'));

        return ApiResponse::created(new FuelRecordResource($r), 'Fuel record saved.');
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Enums\DeliveryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Tracking\LocationRequest;
use App\Models\Delivery;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Services\Tracking\LocationService;
use App\Support\ApiResponse;
use App\Support\Geo;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TrackingController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function store(LocationRequest $request, LocationService $locations): JsonResponse
    {
        $result = $locations->ingest($this->tenant->companyOrFail(), $request->user(), $request->validated());

        return ApiResponse::ok(['status' => $result['status']], match ($result['status']) {
            'stored' => 'Location recorded.', 'duplicate' => 'Duplicate location ignored.', default => 'Location throttled.',
        }, $result['status'] === 'stored' ? 201 : 200);
    }

    /** Live map feed: every vehicle with its last known position and what it is doing. */
    public function vehicles(): JsonResponse
    {
        $vehicles = Vehicle::with(['type', 'activeAssignment.driver', 'lastDriver'])->get();
        $active = Delivery::whereIn('status', DeliveryStatus::activeValues())->whereNotNull('assigned_vehicle_id')
            ->get(['id', 'reference_number', 'status', 'assigned_vehicle_id', 'delivery_address', 'delivery_latitude', 'delivery_longitude', 'recipient_name', 'scheduled_delivery_at'])
            ->groupBy('assigned_vehicle_id');

        return ApiResponse::ok($vehicles->map(fn (Vehicle $v) => $this->presentVehicle($v, $active->get($v->id, collect())))->values());
    }

    public function vehicle(Vehicle $vehicle): JsonResponse
    {
        $active = Delivery::where('assigned_vehicle_id', $vehicle->id)->whereIn('status', DeliveryStatus::activeValues())->get();

        return ApiResponse::ok($this->presentVehicle($vehicle->load('type', 'activeAssignment.driver', 'lastDriver'), $active));
    }

    public function history(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate(['from' => ['nullable', 'date'], 'to' => ['nullable', 'date'], 'limit' => ['nullable', 'integer', 'max:5000']]);
        $from = isset($data['from']) ? now()->parse($data['from']) : now()->subHours(24);
        $to = isset($data['to']) ? now()->parse($data['to']) : now();

        $points = VehicleLocation::where('vehicle_id', $vehicle->id)->whereBetween('recorded_at', [$from, $to])
            ->orderBy('recorded_at')->limit($data['limit'] ?? 2000)
            ->get(['latitude', 'longitude', 'speed', 'heading', 'accuracy', 'recorded_at', 'driver_id', 'delivery_id']);

        return ApiResponse::ok([
            'vehicle_id' => $vehicle->id,
            'from' => $from->toIso8601String(), 'to' => $to->toIso8601String(),
            'points' => $points->map(fn ($p) => [
                'latitude' => $p->latitude, 'longitude' => $p->longitude, 'speed' => $p->speed, 'heading' => $p->heading,
                'accuracy' => $p->accuracy, 'recorded_at' => $p->recorded_at->toIso8601String(), 'delivery_id' => $p->delivery_id,
            ]),
            'distance_km' => Geo::pathKm($points->map(fn ($p) => [$p->latitude, $p->longitude])->all()),
        ]);
    }

    public function delivery(Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);
        $vehicle = $delivery->vehicle;
        $trail = $delivery->actual_pickup_at
            ? VehicleLocation::where('delivery_id', $delivery->id)->orderBy('recorded_at')->limit(2000)->get(['latitude', 'longitude', 'recorded_at'])
            : collect();

        return ApiResponse::ok([
            'delivery_id' => $delivery->id,
            'status' => $delivery->status->value,
            'pickup' => ['latitude' => $delivery->pickup_latitude, 'longitude' => $delivery->pickup_longitude, 'address' => $delivery->pickup_address],
            'dropoff' => ['latitude' => $delivery->delivery_latitude, 'longitude' => $delivery->delivery_longitude, 'address' => $delivery->delivery_address],
            'vehicle' => $vehicle && $vehicle->last_located_at && $delivery->status->isActive() ? [
                'id' => $vehicle->id, 'registration_number' => $vehicle->registration_number,
                'latitude' => $vehicle->last_latitude, 'longitude' => $vehicle->last_longitude, 'heading' => $vehicle->last_heading,
                'recorded_at' => $vehicle->last_located_at->toIso8601String(), 'freshness' => LocationService::freshness($vehicle->last_located_at),
            ] : null,
            'trail' => $trail->map(fn ($p) => [$p->latitude, $p->longitude]),
        ]);
    }

    private function presentVehicle(Vehicle $v, $deliveries): array
    {
        $driver = $v->activeAssignment?->driver ?? $v->lastDriver;
        $current = $deliveries->sortBy(fn ($d) => array_search($d->status->value, ['in_transit', 'arrived', 'picked_up', 'accepted', 'assigned']))->first();

        return [
            'id' => $v->id,
            'registration_number' => $v->registration_number,
            'label' => $v->label(),
            'type' => $v->type?->name,
            'status' => $v->status->value,
            'driver' => $driver ? ['id' => $driver->id, 'name' => $driver->fullName(), 'phone' => $driver->phone] : null,
            'location' => $v->last_located_at ? [
                'latitude' => $v->last_latitude, 'longitude' => $v->last_longitude, 'speed' => $v->last_speed, 'heading' => $v->last_heading,
                'recorded_at' => $v->last_located_at->toIso8601String(), 'freshness' => LocationService::freshness($v->last_located_at),
            ] : null,
            'current_delivery' => $current ? [
                'id' => $current->id, 'reference_number' => $current->reference_number, 'status' => $current->status->value,
                'recipient' => $current->recipient_name, 'destination' => $current->delivery_address,
                'destination_latitude' => $current->delivery_latitude, 'destination_longitude' => $current->delivery_longitude,
                'scheduled_delivery_at' => $current->scheduled_delivery_at?->toIso8601String(),
            ] : null,
            'active_deliveries' => $deliveries->count(),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Dispatch;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Dispatch\AssignRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\VehicleResource;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Dispatch\AssignmentService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DispatchController extends Controller
{
    public function __construct(private readonly AssignmentService $assignments) {}

    public const COLUMNS = [
        'unassigned' => ['pending', 'draft'],
        'assigned' => ['assigned'],
        'accepted' => ['accepted'],
        'picked_up' => ['picked_up'],
        'in_transit' => ['in_transit', 'arrived'],
        'completed' => ['delivered'],
        'failed' => ['failed', 'returned'],
    ];

    public function board(Request $request): JsonResponse
    {
        $date = $request->query('date', now()->toDateString());
        $base = Delivery::with(['customer', 'driver', 'vehicle', 'currentAssignment'])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('reference_number', 'like', $this->like($s))->orWhere('recipient_name', 'like', $this->like($s))->orWhere('delivery_address', 'like', $this->like($s))))
            ->when($request->query('priority'), fn ($q, $p) => $q->whereIn('priority', explode(',', $p)))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('assigned_driver_id', $d))
            ->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('assigned_vehicle_id', $v))
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)));

        $columns = [];
        foreach (self::COLUMNS as $key => $statuses) {
            $q = (clone $base)->whereIn('status', $statuses);
            // Open work is shown regardless of date; closed work only for the selected day.
            if (in_array($key, ['completed', 'failed'], true)) {
                $q->whereDate('updated_at', $date);
            }
            $items = $q->orderByRaw("case priority when 'urgent' then 0 when 'high' then 1 when 'normal' then 2 else 3 end")
                ->orderBy('scheduled_delivery_at')->limit(100)->get();
            $columns[] = ['key' => $key, 'statuses' => $statuses, 'count' => $items->count(), 'deliveries' => DeliveryResource::collection($items)->resolve($request)];
        }

        return ApiResponse::ok(['date' => $date, 'columns' => $columns]);
    }

    public function availableDrivers(Request $request): JsonResponse
    {
        $delivery = $request->query('delivery_id') ? Delivery::findOrFail($request->query('delivery_id')) : null;

        return ApiResponse::ok($this->assignments->availableDrivers($delivery)->map(fn ($r) => [
            'driver' => (new DriverResource($r['driver']))->resolve($request),
            'vehicle' => $r['vehicle'] ? ['id' => $r['vehicle']->id, 'registration_number' => $r['vehicle']->registration_number, 'label' => $r['vehicle']->label()] : null,
            'active_deliveries' => $r['active_deliveries'],
            'eligible' => $r['eligible'],
            'reasons' => $r['reasons'],
        ])->values());
    }

    public function availableVehicles(Request $request): JsonResponse
    {
        $delivery = $request->query('delivery_id') ? Delivery::findOrFail($request->query('delivery_id')) : null;
        $driver = $request->query('driver_id') ? Driver::findOrFail($request->query('driver_id')) : null;

        return ApiResponse::ok($this->assignments->availableVehicles($delivery, $driver)->map(fn ($r) => [
            'vehicle' => (new VehicleResource($r['vehicle']))->resolve($request),
            'load' => $r['load'], 'free_capacity' => $r['free_capacity'], 'eligible' => $r['eligible'], 'reasons' => $r['reasons'],
        ])->sortByDesc('eligible')->values());
    }

    /** Dry-run validation used by the assignment dialog. */
    public function check(AssignRequest $request): JsonResponse
    {
        $errors = $this->assignments->eligibility(
            Delivery::findOrFail($request->validated('delivery_id')),
            Driver::findOrFail($request->validated('driver_id')),
            Vehicle::with('type')->findOrFail($request->validated('vehicle_id')),
        );

        return ApiResponse::ok(['eligible' => $errors === [], 'reasons' => $errors]);
    }

    public function store(AssignRequest $request): JsonResponse
    {
        $delivery = Delivery::findOrFail($request->validated('delivery_id'));
        $a = $this->assignments->assign($delivery, Driver::findOrFail($request->validated('driver_id')), Vehicle::findOrFail($request->validated('vehicle_id')), $request->user());

        return ApiResponse::created($this->present($a->load('delivery', 'driver', 'vehicle', 'assigner')), 'Delivery assigned.');
    }

    public function index(Request $request): JsonResponse
    {
        $q = DeliveryAssignment::with(['delivery', 'driver', 'vehicle', 'assigner'])
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('driver_id', $d))
            ->when($request->query('delivery_id'), fn ($q, $d) => $q->where('delivery_id', $d))
            ->latest('id');
        $page = $q->paginate(min(100, (int) $request->integer('per_page', 25)));

        return ApiResponse::ok(collect($page->items())->map(fn ($a) => $this->present($a))->all(), meta: ['pagination' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function destroy(Request $request, DeliveryAssignment $assignment): JsonResponse
    {
        $this->assignments->unassign($assignment, $request->user());

        return ApiResponse::ok(null, 'Assignment withdrawn.');
    }

    private function present(DeliveryAssignment $a): array
    {
        return [
            'id' => $a->id, 'status' => $a->status->value,
            'delivery' => ['id' => $a->delivery->id, 'reference_number' => $a->delivery->reference_number, 'status' => $a->delivery->status->value],
            'driver' => ['id' => $a->driver->id, 'name' => $a->driver->fullName()],
            'vehicle' => $a->vehicle ? ['id' => $a->vehicle->id, 'registration_number' => $a->vehicle->registration_number] : null,
            'assigned_by' => $a->assigner?->name, 'reason' => $a->response_reason,
            'created_at' => $a->created_at->toIso8601String(), 'responded_at' => $a->responded_at?->toIso8601String(),
        ];
    }
}

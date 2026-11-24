<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DeliveryStatus;
use App\Enums\DriverAvailability;
use App\Enums\DriverStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DocumentRequest;
use App\Http\Requests\Api\V1\Driver\AvailabilityRequest;
use App\Http\Requests\Api\V1\Driver\DriverRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\DriverResource;
use App\Http\Resources\VehicleAssignmentResource;
use App\Models\Driver;
use App\Models\DriverShift;
use App\Services\Driver\DriverPerformanceService;
use App\Services\Driver\DriverService;
use App\Support\ApiResponse;
use App\Support\DateRange;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function __construct(private readonly DriverService $drivers, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $q = Driver::with(['activeVehicleAssignment.vehicle', 'openShift'])
            ->withCount(['deliveries as active_deliveries' => fn ($q) => $q->whereIn('status', DeliveryStatus::activeValues())])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('first_name', 'like', $this->like($s))
                ->orWhere('last_name', 'like', $this->like($s))->orWhere('phone', 'like', $this->like($s))->orWhere('email', 'like', $this->like($s))))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('availability'), fn ($q, $s) => $q->where('availability', $s))
            ->when($request->boolean('license_expiring'), fn ($q) => $q->where('license_expiry_date', '<=', now()->addDays(30)));

        return $this->paginated($this->sort($q, $request, ['first_name', 'last_name', 'license_expiry_date', 'created_at'], 'first_name'), DriverResource::class, $request);
    }

    public function store(DriverRequest $request): JsonResponse
    {
        $d = $this->drivers->create($this->tenant->companyOrFail(), $request->driverData(), $request->file('photo'));

        return ApiResponse::created(new DriverResource($d), 'Driver added.');
    }

    public function show(Driver $driver): JsonResponse
    {
        return ApiResponse::ok(new DriverResource($driver->load('activeVehicleAssignment.vehicle', 'openShift')->loadCount(['deliveries as active_deliveries' => fn ($q) => $q->whereIn('status', DeliveryStatus::activeValues())])));
    }

    public function update(DriverRequest $request, Driver $driver): JsonResponse
    {
        $this->requireManage($request);
        $d = $this->drivers->update($driver, $request->driverData(), $request->file('photo'));

        return ApiResponse::ok(new DriverResource($d->load('activeVehicleAssignment.vehicle')), 'Driver updated.');
    }

    public function destroy(Request $request, Driver $driver): JsonResponse
    {
        $this->requireManage($request);
        $this->drivers->archive($driver);

        return ApiResponse::ok(null, 'Driver archived.');
    }

    public function setStatus(Request $request, Driver $driver): JsonResponse
    {
        $this->requireManage($request);
        $data = $request->validate(['status' => ['required', Rule::enum(DriverStatus::class)]]);

        return ApiResponse::ok(new DriverResource($this->drivers->setStatus($driver, DriverStatus::from($data['status']))), 'Status updated.');
    }

    public function availability(AvailabilityRequest $request, Driver $driver): JsonResponse
    {
        $this->requireManage($request);
        $d = $this->drivers->setAvailability($driver, DriverAvailability::from($request->validated('availability')), $request->user(),
            $request->validated('starts_at'), $request->validated('ends_at'), $request->validated('reason'));

        return ApiResponse::ok(new DriverResource($d), 'Availability updated.');
    }

    public function availabilityHistory(Driver $driver): JsonResponse
    {
        return ApiResponse::ok($driver->availabilities()->latest('starts_at')->limit(100)->get()->map(fn ($a) => [
            'id' => $a->id, 'status' => $a->status->value, 'starts_at' => $a->starts_at->toIso8601String(), 'ends_at' => $a->ends_at?->toIso8601String(), 'reason' => $a->reason,
        ]));
    }

    public function shifts(Driver $driver): JsonResponse
    {
        return ApiResponse::ok(DriverShift::where('driver_id', $driver->id)->latest('started_at')->limit(100)->get()->map(fn ($s) => [
            'id' => $s->id, 'started_at' => $s->started_at->toIso8601String(), 'ended_at' => $s->ended_at?->toIso8601String(),
            'duration_minutes' => $s->duration_minutes ?? ($s->ended_at ? null : (int) $s->started_at->diffInMinutes(now())),
        ]));
    }

    public function deliveries(Request $request, Driver $driver): JsonResponse
    {
        $q = $driver->deliveries()->with(['customer', 'vehicle'])->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)));

        return $this->paginated($q->latest(), DeliveryResource::class, $request);
    }

    public function performance(Request $request, Driver $driver, DriverPerformanceService $perf): JsonResponse
    {
        $range = DateRange::fromRequest($request, $this->tenant->companyOrFail()->timezone, 'last_90_days');

        return ApiResponse::ok(['range' => $range->toArray()] + $perf->metrics($driver, $range->from, $range->to));
    }

    public function assignments(Driver $driver): JsonResponse
    {
        return ApiResponse::ok(VehicleAssignmentResource::collection($driver->vehicleAssignments()->with('vehicle', 'assigner', 'driver')->latest('assigned_at')->limit(100)->get()));
    }

    public function documents(Request $request, Driver $driver): JsonResponse
    {
        if (! $request->user()->can('drivers.view_private')) {
            throw new AuthorizationException;
        }

        return ApiResponse::ok(DocumentResource::collection($driver->documents()->latest()->get()));
    }

    public function storeDocument(DocumentRequest $request, Driver $driver): JsonResponse
    {
        $this->requireManage($request);
        $doc = $this->drivers->addDocument($driver, collect($request->validated())->except('file')->all(), $request->file('file'));

        return ApiResponse::created(new DocumentResource($doc), 'Document uploaded.');
    }

    private function requireManage(Request $request): void
    {
        if (! $request->user()->can('drivers.manage')) {
            throw new AuthorizationException;
        }
    }
}

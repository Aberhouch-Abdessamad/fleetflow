<?php

namespace App\Http\Controllers\Api\V1\Fleet;

use App\Enums\VehicleStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\DocumentRequest;
use App\Http\Requests\Api\V1\Fleet\VehicleRequest;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ExpenseResource;
use App\Http\Resources\FuelRecordResource;
use App\Http\Resources\MaintenanceResource;
use App\Http\Resources\VehicleAssignmentResource;
use App\Http\Resources\VehicleResource;
use App\Http\Resources\VehicleTypeResource;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\VehicleType;
use App\Services\Fleet\VehicleAssignmentService;
use App\Services\Fleet\VehicleService;
use App\Services\Fuel\FuelService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function __construct(private readonly VehicleService $vehicles, private readonly TenantContext $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $q = Vehicle::with(['type', 'activeAssignment.driver'])
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('registration_number', 'like', $this->like($s))
                ->orWhere('make', 'like', $this->like($s))->orWhere('model', 'like', $this->like($s))->orWhere('vin', 'like', $this->like($s))))
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('vehicle_type_id'), fn ($q, $t) => $q->where('vehicle_type_id', $t))
            ->when($request->boolean('archived'), fn ($q) => $q->onlyTrashed());

        return $this->paginated($this->sort($q, $request, ['registration_number', 'make', 'odometer', 'next_service_date', 'created_at', 'status'], 'registration_number'), VehicleResource::class, $request);
    }

    public function store(VehicleRequest $request): JsonResponse
    {
        $v = $this->vehicles->create($this->tenant->companyOrFail(), $request->vehicleData(), $request->file('image'));

        return ApiResponse::created(new VehicleResource($v->load('type', 'activeAssignment.driver')), 'Vehicle added.');
    }

    public function show(Vehicle $vehicle): JsonResponse
    {
        return ApiResponse::ok(new VehicleResource($vehicle->load('type', 'activeAssignment.driver')));
    }

    public function update(VehicleRequest $request, Vehicle $vehicle): JsonResponse
    {
        $v = $this->vehicles->update($vehicle, $request->vehicleData(), $request->file('image'));

        return ApiResponse::ok(new VehicleResource($v->load('type', 'activeAssignment.driver')), 'Vehicle updated.');
    }

    public function destroy(Vehicle $vehicle): JsonResponse
    {
        $this->vehicles->archive($vehicle);

        return ApiResponse::ok(null, 'Vehicle archived.');
    }

    public function restore(int $id): JsonResponse
    {
        $v = Vehicle::onlyTrashed()->findOrFail($id);
        $v->restore();
        $v->forceFill(['status' => VehicleStatus::Available])->save();

        return ApiResponse::ok(new VehicleResource($v->load('type')), 'Vehicle restored.');
    }

    public function setStatus(Request $request, Vehicle $vehicle): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['available', 'out_of_service', 'inactive'])],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);
        $v = $this->vehicles->setStatus($vehicle, VehicleStatus::from($data['status']), $data['reason'] ?? null);

        return ApiResponse::ok(new VehicleResource($v->load('type', 'activeAssignment.driver')), 'Status updated.');
    }

    /** Combined history feed for the detail page tabs. */
    public function history(Request $request, Vehicle $vehicle, FuelService $fuel): JsonResponse
    {
        $tab = $request->query('tab', 'assignments');
        $data = match ($tab) {
            'assignments' => VehicleAssignmentResource::collection($vehicle->assignments()->with(['driver', 'assigner', 'vehicle'])->latest('assigned_at')->limit(100)->get()),
            'deliveries' => DeliveryResource::collection($vehicle->deliveries()->with('driver', 'customer')->latest()->limit(100)->get()),
            'maintenance' => MaintenanceResource::collection($vehicle->maintenanceRecords()->latest('scheduled_date')->limit(100)->get()),
            'fuel' => [
                'records' => FuelRecordResource::collection($vehicle->fuelRecords()->with('driver')->latest('recorded_at')->limit(100)->get()),
                'efficiency' => $fuel->efficiency($vehicle->fuelRecords()->get()),
            ],
            'expenses' => ExpenseResource::collection($vehicle->expenses()->with('creator')->latest('expense_date')->limit(100)->get()),
            default => [],
        };

        return ApiResponse::ok($data instanceof JsonResource ? $data->resolve($request) : $data);
    }

    public function documents(Vehicle $vehicle): JsonResponse
    {
        return ApiResponse::ok(DocumentResource::collection($vehicle->documents()->latest()->get()));
    }

    public function storeDocument(DocumentRequest $request, Vehicle $vehicle): JsonResponse
    {
        $doc = $this->vehicles->addDocument($vehicle, collect($request->validated())->except('file')->all(), $request->file('file'));

        return ApiResponse::created(new DocumentResource($doc), 'Document uploaded.');
    }

    public function destroyDocument(Vehicle $vehicle, int $document): JsonResponse
    {
        $vehicle->documents()->findOrFail($document)->delete();

        return ApiResponse::ok(null, 'Document removed.');
    }

    public function assignDriver(Request $request, Vehicle $vehicle, VehicleAssignmentService $service): JsonResponse
    {
        $data = $request->validate([
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $this->tenant->id())->whereNull('deleted_at')],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        $a = $service->assign($vehicle, Driver::findOrFail($data['driver_id']), $request->user(), $data['notes'] ?? null);

        return ApiResponse::created(new VehicleAssignmentResource($a->load('driver', 'vehicle', 'assigner')), 'Driver assigned to vehicle.');
    }

    public function unassignDriver(Request $request, Vehicle $vehicle, VehicleAssignmentService $service): JsonResponse
    {
        $a = VehicleAssignment::where('vehicle_id', $vehicle->id)->whereNull('unassigned_at')->firstOrFail();
        $service->unassign($a, $request->user());

        return ApiResponse::ok(null, 'Driver unassigned.');
    }

    public function types(): JsonResponse
    {
        return ApiResponse::ok(VehicleTypeResource::collection(VehicleType::orderBy('name')->get()));
    }

    public function storeType(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'alpha_dash', 'max:50', Rule::unique('vehicle_types')->where('company_id', $this->tenant->id())],
            'required_license' => ['nullable', Rule::in(['A', 'B', 'C', 'CE', 'D'])],
            'default_capacity' => ['nullable', 'numeric', 'min:0'],
            'capacity_unit' => ['sometimes', Rule::in(['kg', 't', 'm3', 'pallets'])],
        ]);
        $type = new VehicleType($data);
        $type->company_id = $this->tenant->id();
        $type->save();

        return ApiResponse::created(new VehicleTypeResource($type));
    }
}

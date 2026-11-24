<?php

namespace App\Http\Controllers\Api\V1\Driver;

use App\Enums\DeliveryStatus;
use App\Events\DeliveryExceptionReported;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Resources\DeliveryResource;
use App\Http\Resources\DriverResource;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Notifications\DeliveryStatusNotification;
use App\Services\Dispatch\AssignmentService;
use App\Services\Driver\DriverPerformanceService;
use App\Services\Driver\ShiftService;
use App\Services\Notify;
use App\Services\Tenant\CompanySettings;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Endpoints for the mobile driver experience. The driver is always the authenticated user's own profile. */
class DriverAppController extends Controller
{
    public function __construct(private readonly TenantContext $tenant) {}

    private function me(Request $request): Driver
    {
        return Driver::with(['activeVehicleAssignment.vehicle.type', 'openShift'])->where('user_id', $request->user()->id)->first()
            ?? throw new DomainException('No driver profile is linked to your account in this company.', 'not_a_driver', 403);
    }

    public function dashboard(Request $request, CompanySettings $settings, DriverPerformanceService $perf): JsonResponse
    {
        $driver = $this->me($request);
        $today = now($this->tenant->companyOrFail()->timezone)->startOfDay()->utc();
        $active = $driver->deliveries()->with(['customer', 'vehicle', 'items', 'currentAssignment', 'proofs'])
            ->whereIn('status', DeliveryStatus::activeValues())
            ->orderByRaw("case status when 'in_transit' then 0 when 'arrived' then 1 when 'picked_up' then 2 when 'accepted' then 3 else 4 end")
            ->orderBy('scheduled_delivery_at')->get();
        $completedToday = $driver->deliveries()->where('status', 'delivered')->where('actual_delivery_at', '>=', $today)->count();

        return ApiResponse::ok([
            'driver' => (new DriverResource($driver))->resolve($request),
            'on_shift' => $driver->openShift !== null,
            'shift_started_at' => $driver->openShift?->started_at?->toIso8601String(),
            'can_reject' => (bool) $settings->get($driver->company, 'drivers_can_reject_assignments'),
            'pending_assignments' => DeliveryResource::collection($active->where('status', DeliveryStatus::Assigned)->values())->resolve($request),
            'active' => DeliveryResource::collection($active->where('status', '!=', DeliveryStatus::Assigned)->values())->resolve($request),
            'stats' => [
                'completed_today' => $completedToday,
                'active' => $active->count(),
            ] + $perf->metrics($driver, $today, now()),
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $driver = $this->me($request);

        return $this->paginated($driver->deliveries()->with('customer')->whereIn('status', ['delivered', 'failed', 'returned', 'cancelled'])->latest('updated_at'), DeliveryResource::class, $request);
    }

    public function delivery(Request $request, Delivery $delivery): JsonResponse
    {
        $driver = $this->me($request);
        if ($delivery->assigned_driver_id !== $driver->id) {
            throw new DomainException('This delivery is not assigned to you.', 'forbidden', 403);
        }

        return ApiResponse::ok(new DeliveryResource($delivery->load(['customer', 'vehicle', 'items', 'statusHistory.user', 'proofs', 'currentAssignment'])));
    }

    public function respond(Request $request, DeliveryAssignment $assignment, AssignmentService $service): JsonResponse
    {
        $data = $request->validate(['accept' => ['required', 'boolean'], 'reason' => ['nullable', 'string', 'max:500']]);
        $service->respond($assignment, $this->me($request), (bool) $data['accept'], $data['reason'] ?? null);

        return ApiResponse::ok(null, $data['accept'] ? 'Assignment accepted.' : 'Assignment rejected.');
    }

    public function startShift(Request $request, ShiftService $shifts): JsonResponse
    {
        $shift = $shifts->start($this->me($request));

        return ApiResponse::ok(['started_at' => $shift->started_at->toIso8601String()], 'Shift started.');
    }

    public function endShift(Request $request, ShiftService $shifts): JsonResponse
    {
        $shift = $shifts->end($this->me($request));

        return ApiResponse::ok(['duration_minutes' => $shift?->duration_minutes], 'Shift ended.');
    }

    /** Driver reports a problem without necessarily failing the delivery yet. */
    public function reportIssue(Request $request, Delivery $delivery): JsonResponse
    {
        $driver = $this->me($request);
        if ($delivery->assigned_driver_id !== $driver->id) {
            throw new DomainException('This delivery is not assigned to you.', 'forbidden', 403);
        }
        $data = $request->validate([
            'type' => ['required', 'in:address_not_found,recipient_unavailable,damaged_goods,vehicle_problem,refused,other'],
            'notes' => ['nullable', 'string', 'max:500'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);
        $delivery->statusHistory()->create([
            'previous_status' => $delivery->status->value, 'new_status' => $delivery->status->value, 'changed_by' => $request->user()->id,
            'source' => 'driver', 'notes' => 'Issue reported: '.str_replace('_', ' ', $data['type']).($data['notes'] ? " — {$data['notes']}" : ''),
            'latitude' => $data['latitude'] ?? null, 'longitude' => $data['longitude'] ?? null, 'changed_at' => now(),
        ]);
        $company = $delivery->company;
        event(new DeliveryExceptionReported($company->id, $company->public_id, [
            'delivery_id' => $delivery->id, 'reference' => $delivery->reference_number, 'type' => $data['type'], 'notes' => $data['notes'] ?? null, 'driver_id' => $driver->id,
        ]));
        Notify::companyUsersWith($company, 'deliveries.assign', new DeliveryStatusNotification(
            $company->id, $delivery->id, $delivery->reference_number, 'issue reported', str_replace('_', ' ', $data['type']).($data['notes'] ? ": {$data['notes']}" : '')
        ));

        return ApiResponse::ok(null, 'Issue reported to dispatch.');
    }
}

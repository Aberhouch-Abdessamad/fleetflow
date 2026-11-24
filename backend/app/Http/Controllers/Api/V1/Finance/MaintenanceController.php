<?php

namespace App\Http\Controllers\Api\V1\Finance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Finance\MaintenanceRequest;
use App\Http\Resources\MaintenanceResource;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceReminder;
use App\Services\Maintenance\MaintenanceService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    public function __construct(private readonly MaintenanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        $q = MaintenanceRecord::with('vehicle')
            ->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('vehicle_id', $v))
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('type'), fn ($q, $t) => $q->where('maintenance_type', $t))
            ->when($request->boolean('overdue'), fn ($q) => $q->where('status', 'scheduled')->where('scheduled_date', '<', now()->toDateString()))
            ->when($request->query('from'), fn ($q, $d) => $q->where(fn ($w) => $w->where('scheduled_date', '>=', $d)->orWhere('completed_date', '>=', $d)))
            ->when($request->query('to'), fn ($q, $d) => $q->where(fn ($w) => $w->where('scheduled_date', '<=', $d)->orWhere('completed_date', '<=', $d)));

        return $this->paginated($this->sort($q, $request, ['scheduled_date', 'completed_date', 'cost', 'created_at'], '-scheduled_date'), MaintenanceResource::class, $request, 50);
    }

    public function store(MaintenanceRequest $request): JsonResponse
    {
        $r = $this->service->create(collect($request->validated())->except('invoice')->all(), $request->user(), $request->file('invoice'));

        return ApiResponse::created(new MaintenanceResource($r), 'Maintenance saved.');
    }

    public function show(MaintenanceRecord $maintenance): JsonResponse
    {
        return ApiResponse::ok(new MaintenanceResource($maintenance->load('vehicle')));
    }

    public function update(MaintenanceRequest $request, MaintenanceRecord $maintenance): JsonResponse
    {
        $r = $this->service->update($maintenance, collect($request->validated())->except('invoice')->all(), $request->user(), $request->file('invoice'));

        return ApiResponse::ok(new MaintenanceResource($r), 'Maintenance updated.');
    }

    public function destroy(MaintenanceRecord $maintenance): JsonResponse
    {
        $this->service->delete($maintenance);

        return ApiResponse::ok(null, 'Maintenance deleted.');
    }

    public function reminders(Request $request): JsonResponse
    {
        $rows = MaintenanceReminder::with('vehicle:id,registration_number,make,model')->where('status', $request->query('status', 'open'))
            ->orderBy('due_date')->limit(200)->get();

        return ApiResponse::ok($rows->map(fn ($r) => [
            'id' => $r->id, 'type' => $r->reminder_type, 'vehicle' => $r->vehicle ? ['id' => $r->vehicle->id, 'registration_number' => $r->vehicle->registration_number] : null,
            'due_date' => $r->due_date?->toDateString(), 'due_odometer' => $r->due_odometer, 'status' => $r->status,
            'overdue' => $r->due_date?->isPast() ?? false, 'notified_at' => $r->notified_at?->toIso8601String(),
        ]));
    }

    public function dismissReminder(MaintenanceReminder $reminder): JsonResponse
    {
        $reminder->forceFill(['status' => 'dismissed'])->save();

        return ApiResponse::ok(null, 'Reminder dismissed.');
    }
}

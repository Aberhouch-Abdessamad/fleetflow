<?php

namespace App\Http\Controllers\Api\V1\Delivery;

use App\Enums\DeliveryStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Delivery\DeliveryRequest;
use App\Http\Requests\Api\V1\Delivery\ProofRequest;
use App\Http\Requests\Api\V1\Delivery\StatusRequest;
use App\Http\Requests\Api\V1\Dispatch\AssignRequest;
use App\Http\Resources\DeliveryResource;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Services\Delivery\DeliveryService;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Delivery\ProofService;
use App\Services\Dispatch\AssignmentService;
use App\Services\Reports\Exporter;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    private const DETAIL = ['customer', 'driver', 'vehicle', 'items', 'statusHistory.user', 'assignments.driver', 'assignments.vehicle', 'assignments.assigner', 'proofs', 'currentAssignment', 'invoiceItem'];

    public function __construct(
        private readonly DeliveryService $deliveries,
        private readonly DeliveryStatusService $status,
        private readonly TenantContext $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->paginated($this->filtered($request), DeliveryResource::class, $request);
    }

    public function export(Request $request, Exporter $exporter)
    {
        abort_unless($request->user()->can('deliveries.view'), 403);
        $rows = $this->filtered($request)->limit(10000)->get()->map(fn (Delivery $d) => [
            'reference' => $d->reference_number, 'status' => $d->status->value, 'priority' => $d->priority->value,
            'customer' => $d->customer?->name, 'recipient' => $d->recipient_name, 'recipient_phone' => $d->recipient_phone,
            'pickup_address' => $d->pickup_address, 'delivery_address' => $d->delivery_address, 'city' => $d->delivery_city,
            'driver' => $d->driver?->fullName(), 'vehicle' => $d->vehicle?->registration_number,
            'scheduled_delivery_at' => $d->scheduled_delivery_at?->toDateTimeString(), 'delivered_at' => $d->actual_delivery_at?->toDateTimeString(),
            'weight_kg' => $d->total_weight, 'delivery_fee' => $d->delivery_fee, 'cod_amount' => $d->cod_amount,
            'failure_reason' => $d->failure_reason, 'created_at' => $d->created_at->toDateTimeString(),
        ])->all();

        return $exporter->download($request->query('format', 'csv'), 'deliveries-'.now()->format('Ymd-His'), $rows);
    }

    private function filtered(Request $request): Builder
    {
        $user = $request->user();
        $q = Delivery::query()->with(['customer', 'driver', 'vehicle']);

        // Users who may only see their own deliveries (drivers) are restricted here.
        if (! $user->can('deliveries.view')) {
            if (! $user->can('deliveries.view_own')) {
                throw new AuthorizationException;
            }
            $driverId = Driver::where('user_id', $user->id)->value('id');
            $q->where('assigned_driver_id', $driverId ?? 0);
        }

        $q->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('reference_number', 'like', $this->like($s))
            ->orWhere('recipient_name', 'like', $this->like($s))->orWhere('external_reference', 'like', $this->like($s))
            ->orWhere('delivery_address', 'like', $this->like($s))->orWhere('recipient_phone', 'like', $this->like($s))))
            ->when($request->query('status'), fn ($q, $s) => $q->whereIn('status', explode(',', $s)))
            ->when($request->query('priority'), fn ($q, $p) => $q->whereIn('priority', explode(',', $p)))
            ->when($request->query('driver_id'), fn ($q, $d) => $q->where('assigned_driver_id', $d))
            ->when($request->query('vehicle_id'), fn ($q, $v) => $q->where('assigned_vehicle_id', $v))
            ->when($request->query('customer_id'), fn ($q, $c) => $q->where('customer_id', $c))
            ->when($request->query('unassigned'), fn ($q) => $q->whereNull('assigned_driver_id'))
            ->when($request->query('unrouted'), fn ($q) => $q->whereNull('route_id'))
            ->when($request->query('date'), fn ($q, $d) => $q->where(fn ($w) => $w->whereDate('scheduled_delivery_at', $d)->orWhere(fn ($x) => $x->whereNull('scheduled_delivery_at')->whereDate('created_at', $d))))
            ->when($request->query('from'), fn ($q, $d) => $q->where('created_at', '>=', $d))
            ->when($request->query('to'), fn ($q, $d) => $q->where('created_at', '<=', $d.' 23:59:59'));

        return $this->sort($q, $request, ['created_at', 'scheduled_delivery_at', 'reference_number', 'status', 'priority'], '-created_at');
    }

    public function store(DeliveryRequest $request): JsonResponse
    {
        [$delivery, $otp] = $this->deliveries->create($this->tenant->companyOrFail(), $request->validated(), $request->user());

        return ApiResponse::created(['delivery' => (new DeliveryResource($delivery->load(self::DETAIL)))->resolve($request), 'otp' => $otp],
            $otp ? 'Delivery created. Share the delivery code with the recipient.' : 'Delivery created.');
    }

    public function show(Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return ApiResponse::ok(new DeliveryResource($delivery->load(self::DETAIL)));
    }

    public function update(DeliveryRequest $request, Delivery $delivery): JsonResponse
    {
        $d = $this->deliveries->update($delivery, $request->validated(), $request->user());

        return ApiResponse::ok(new DeliveryResource($d->load(self::DETAIL)), 'Delivery updated.');
    }

    public function destroy(Delivery $delivery): JsonResponse
    {
        $this->deliveries->delete($delivery);

        return ApiResponse::ok(null, 'Delivery deleted.');
    }

    public function cancel(Request $request, Delivery $delivery): JsonResponse
    {
        abort_unless($request->user()->can('deliveries.cancel'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->deliveries->cancel($delivery, $request->user(), $data['reason']);

        return ApiResponse::ok(new DeliveryResource($delivery->fresh(self::DETAIL)), 'Delivery cancelled.');
    }

    /**
     * Status changes. Dispatchers (deliveries.update) may make any valid transition;
     * the assigned driver may only progress their own delivery through driver statuses.
     * Assignment/cancellation have dedicated endpoints.
     */
    public function status(StatusRequest $request, Delivery $delivery): JsonResponse
    {
        $to = DeliveryStatus::from($request->validated('status'));
        $user = $request->user();

        if (in_array($to, [DeliveryStatus::Assigned, DeliveryStatus::Accepted, DeliveryStatus::Cancelled], true)) {
            throw new DomainException('Use the assignment or cancellation endpoints for this change.', 'use_dedicated_endpoint');
        }

        $isDispatcher = $user->can('deliveries.update');
        $isOwnDriver = $user->can('progress', $delivery);
        if (! $isDispatcher && ! $isOwnDriver) {
            throw new AuthorizationException;
        }
        if (! $isDispatcher && ! in_array($to, DeliveryStatus::driverSettable(), true)) {
            throw new AuthorizationException;
        }

        $this->status->transition($delivery, $to, $user, $request->validated('notes'),
            $request->validated('latitude'), $request->validated('longitude'),
            $isOwnDriver && ! $isDispatcher ? 'driver' : 'user',
            ['cod_collected_amount' => $request->validated('cod_collected_amount')]);

        return ApiResponse::ok(new DeliveryResource($delivery->fresh(self::DETAIL)), 'Status updated.');
    }

    public function assign(AssignRequest $request, Delivery $delivery, AssignmentService $assignments): JsonResponse
    {
        $assignments->assign($delivery, Driver::findOrFail($request->validated('driver_id')), Vehicle::findOrFail($request->validated('vehicle_id')), $request->user());

        return ApiResponse::ok(new DeliveryResource($delivery->fresh(self::DETAIL)), 'Delivery assigned.');
    }

    public function proof(ProofRequest $request, Delivery $delivery, ProofService $proofs): JsonResponse
    {
        $user = $request->user();
        if (! $user->can('progress', $delivery) && ! $user->can('deliveries.update')) {
            throw new AuthorizationException;
        }
        $driver = Driver::where('user_id', $user->id)->first();
        $proofs->add($delivery, $driver, $request->validated(), $request->file('photo'));

        return ApiResponse::created(new DeliveryResource($delivery->fresh(self::DETAIL)), 'Proof saved.');
    }

    public function history(Delivery $delivery): JsonResponse
    {
        $this->authorize('view', $delivery);

        return ApiResponse::ok((new DeliveryResource($delivery->load('statusHistory.user', 'assignments.driver', 'assignments.vehicle', 'assignments.assigner')))->resolve()['timeline'] ?? []);
    }

    public function regenerateOtp(Request $request, Delivery $delivery): JsonResponse
    {
        abort_unless($request->user()->can('deliveries.update'), 403);
        if ($delivery->status->isTerminal()) {
            throw new DomainException('This delivery is closed.', 'delivery_closed');
        }

        return ApiResponse::ok(['otp' => $this->deliveries->regenerateOtp($delivery)], 'New delivery code generated.');
    }
}

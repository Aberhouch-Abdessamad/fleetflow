<?php

namespace App\Services\Dispatch;

use App\Enums\AssignmentStatus;
use App\Enums\DeliveryStatus;
use App\Enums\DriverAvailability;
use App\Enums\DriverStatus;
use App\Events\DriverAssignmentUpdated;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Notifications\AssignmentNotification;
use App\Services\ActivityLogger;
use App\Services\Delivery\DeliveryStatusService;
use App\Services\Fleet\VehicleAssignmentService;
use App\Services\Fleet\VehicleAvailabilityService;
use App\Services\Notify;
use App\Services\Tenant\CompanySettings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delivery ↔ driver/vehicle assignment. Every rule is checked inside a
 * transaction holding row locks on the delivery, driver and vehicle, so two
 * dispatchers can't create conflicting assignments.
 */
class AssignmentService
{
    public function __construct(
        private readonly DeliveryStatusService $status,
        private readonly VehicleAvailabilityService $availability,
        private readonly VehicleAssignmentService $vehicleAssignments,
        private readonly CompanySettings $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function assign(Delivery $delivery, Driver $driver, Vehicle $vehicle, User $by): DeliveryAssignment
    {
        $result = DB::transaction(function () use ($delivery, $driver, $vehicle, $by) {
            // Lock in a consistent order (delivery → driver → vehicle) to avoid deadlocks.
            $delivery = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();
            $vehicle = Vehicle::with('type')->whereKey($vehicle->id)->lockForUpdate()->firstOrFail();

            if (! in_array($delivery->status, [DeliveryStatus::Pending, DeliveryStatus::Assigned, DeliveryStatus::Accepted], true)) {
                throw new DomainException("A {$delivery->status->label()} delivery cannot be (re)assigned.", 'not_assignable');
            }
            if ($delivery->assigned_driver_id === $driver->id && $delivery->assigned_vehicle_id === $vehicle->id) {
                throw new DomainException('The delivery is already assigned to this driver and vehicle.', 'already_assigned');
            }

            $problems = $this->eligibility($delivery, $driver, $vehicle);
            if ($problems) {
                throw new DomainException($problems[0], 'assignment_invalid');
            }

            $previous = $delivery->assignments()->whereIn('status', ['pending', 'accepted'])->get();
            $isReassign = $previous->isNotEmpty();
            $previous->each(fn ($a) => $a->forceFill(['status' => AssignmentStatus::Superseded])->save());
            $previousVehicleId = $delivery->assigned_vehicle_id;

            // Reassignment goes back through Pending so the history shows each hop.
            if ($delivery->status !== DeliveryStatus::Pending) {
                $this->status->transition($delivery, DeliveryStatus::Pending, $by, 'Reassigned', source: 'dispatch');
            }

            $assignment = DeliveryAssignment::create([
                'delivery_id' => $delivery->id,
                'driver_id' => $driver->id,
                'vehicle_id' => $vehicle->id,
                'assigned_by' => $by->id,
                'status' => AssignmentStatus::Pending,
            ]);
            $delivery->forceFill([
                'assigned_driver_id' => $driver->id,
                'assigned_vehicle_id' => $vehicle->id,
                'assigned_at' => now(),
            ])->save();
            $this->status->transition($delivery, DeliveryStatus::Assigned, $by, "Assigned to {$driver->fullName()} · {$vehicle->registration_number}", source: 'dispatch');

            $this->availability->refresh($vehicle);
            $this->availability->refreshId($previousVehicleId !== $vehicle->id ? $previousVehicleId : null);
            $this->logger->log($isReassign ? 'delivery.reassigned' : 'delivery.assigned', $delivery, [
                'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
            ]);

            return [$assignment, $previous->pluck('driver_id')->unique()->diff([$driver->id])];
        });

        [$assignment, $previousDrivers] = $result;
        $this->announce($assignment, $previousDrivers->isEmpty() ? 'assigned' : 'reassigned');
        foreach ($previousDrivers as $driverId) {
            $this->notifyDriver(Driver::find($driverId), $assignment, 'unassigned');
        }

        return $assignment;
    }

    /** Driver accepts / rejects (rejection only if company policy allows). */
    public function respond(DeliveryAssignment $assignment, Driver $driver, bool $accept, ?string $reason = null): DeliveryAssignment
    {
        if ($assignment->driver_id !== $driver->id) {
            throw new DomainException('This assignment is not yours.', 'forbidden', 403);
        }
        if ($assignment->status !== AssignmentStatus::Pending) {
            throw new DomainException('This assignment has already been answered or withdrawn.', 'assignment_closed', 409);
        }
        if (! $accept && ! $this->settings->get($assignment->delivery->company, 'drivers_can_reject_assignments')) {
            throw new DomainException('Your company does not allow drivers to reject assignments. Contact dispatch.', 'reject_not_allowed', 403);
        }
        if (! $accept && blank($reason)) {
            throw new DomainException('Give a reason for rejecting.', 'reason_required');
        }

        DB::transaction(function () use ($assignment, $driver, $accept, $reason) {
            $assignment->forceFill([
                'status' => $accept ? AssignmentStatus::Accepted : AssignmentStatus::Rejected,
                'responded_at' => now(),
                'response_reason' => $reason,
            ])->save();
            $delivery = $assignment->delivery;
            $user = $driver->user;
            if ($accept) {
                $this->status->transition($delivery, DeliveryStatus::Accepted, $user, 'Accepted by driver', source: 'driver');
            } else {
                $this->status->transition($delivery, DeliveryStatus::Pending, $user, 'Rejected by driver: '.$reason, source: 'driver');
            }
            $this->logger->log($accept ? 'assignment.accepted' : 'assignment.rejected', $delivery, ['driver_id' => $driver->id, 'reason' => $reason]);
        });

        $this->announce($assignment->fresh(), $accept ? 'accepted' : 'rejected');

        return $assignment;
    }

    /** Dispatcher withdraws an assignment before pickup. */
    public function unassign(DeliveryAssignment $assignment, User $by): void
    {
        if (! in_array($assignment->status, [AssignmentStatus::Pending, AssignmentStatus::Accepted], true)) {
            throw new DomainException('This assignment is no longer active.', 'assignment_closed', 409);
        }
        $delivery = $assignment->delivery;
        if (! $delivery->status->canTransitionTo(DeliveryStatus::Pending)) {
            throw new DomainException('The delivery is already underway and cannot be unassigned.', 'delivery_underway');
        }
        DB::transaction(function () use ($assignment, $delivery, $by) {
            $assignment->forceFill(['status' => AssignmentStatus::Cancelled, 'responded_at' => now()])->save();
            $this->status->transition($delivery, DeliveryStatus::Pending, $by, 'Unassigned by dispatch', source: 'dispatch');
            $this->logger->log('delivery.unassigned', $delivery, ['driver_id' => $assignment->driver_id]);
        });
        $this->notifyDriver($assignment->driver, $assignment, 'unassigned');
    }

    /**
     * @return list<string> human-readable reasons why this pairing is not allowed (empty = OK)
     */
    public function eligibility(Delivery $delivery, Driver $driver, Vehicle $vehicle): array
    {
        $errors = [];
        $when = $delivery->scheduled_delivery_at ?? now();

        if ($driver->status !== DriverStatus::Active) {
            $errors[] = "{$driver->fullName()} is not an active driver.";
        }
        if ($driver->availability === DriverAvailability::OnLeave) {
            $errors[] = "{$driver->fullName()} is on leave.";
        }
        if (! $driver->licenseValidOn($when->max(now()))) {
            $errors[] = "{$driver->fullName()}'s licence expires before the delivery date.";
        }
        if (! $driver->licenseCovers($vehicle->type?->required_license)) {
            $errors[] = "Licence category {$driver->license_type} does not cover a {$vehicle->type?->name} (requires {$vehicle->type?->required_license}).";
        }

        try {
            $this->vehicleAssignments->assertVehicleUsable($vehicle);
        } catch (DomainException $e) {
            $errors[] = $e->getMessage();
        }

        $capacity = (float) $vehicle->capacity;
        $load = $this->availability->activeLoad($vehicle, $delivery->id);
        if ($capacity > 0 && $load + (float) $delivery->total_weight > $capacity) {
            $errors[] = sprintf('Insufficient capacity: %s kg needed, %s kg free of %s kg.', $delivery->total_weight, max(0, $capacity - $load), $capacity);
        }

        $driverActive = Delivery::where('assigned_driver_id', $driver->id)->whereIn('status', DeliveryStatus::activeValues())->whereKeyNot($delivery->id);
        $max = (int) $this->settings->get($delivery->company, 'max_active_deliveries_per_driver');
        if ((clone $driverActive)->count() >= $max) {
            $errors[] = "{$driver->fullName()} already has {$max} active deliveries (company limit).";
        }
        if ((clone $driverActive)->where('assigned_vehicle_id', '!=', $vehicle->id)->exists()) {
            $errors[] = "{$driver->fullName()} has active deliveries on a different vehicle.";
        }
        if (Delivery::where('assigned_vehicle_id', $vehicle->id)->whereIn('status', DeliveryStatus::activeValues())
            ->whereKeyNot($delivery->id)->where('assigned_driver_id', '!=', $driver->id)->exists()) {
            $errors[] = "{$vehicle->registration_number} is carrying another driver's deliveries.";
        }
        $vehicleHolder = $vehicle->activeAssignment;
        if ($vehicleHolder && $vehicleHolder->driver_id !== $driver->id && $this->settings->get($delivery->company, 'exclusive_vehicle_assignment')) {
            $errors[] = "{$vehicle->registration_number} is assigned to {$vehicleHolder->driver?->fullName()}.";
        }

        return $errors;
    }

    /** Drivers with an eligibility verdict for a delivery (or general availability without one). */
    public function availableDrivers(?Delivery $delivery): Collection
    {
        $drivers = Driver::with(['activeVehicleAssignment.vehicle.type'])
            ->where('status', DriverStatus::Active)->orderBy('first_name')->get();
        $counts = Delivery::whereIn('status', DeliveryStatus::activeValues())->whereNotNull('assigned_driver_id')
            ->selectRaw('assigned_driver_id, count(*) c')->groupBy('assigned_driver_id')->pluck('c', 'assigned_driver_id');

        return $drivers->map(function (Driver $d) use ($delivery, $counts) {
            $reasons = [];
            if ($d->availability === DriverAvailability::OnLeave) {
                $reasons[] = 'On leave';
            }
            if (! $d->licenseValidOn(($delivery?->scheduled_delivery_at ?? now())->max(now()))) {
                $reasons[] = 'Licence expired';
            }

            return [
                'driver' => $d,
                'active_deliveries' => (int) ($counts[$d->id] ?? 0),
                'vehicle' => $d->activeVehicleAssignment?->vehicle,
                'eligible' => $reasons === [],
                'reasons' => $reasons,
            ];
        });
    }

    public function availableVehicles(?Delivery $delivery, ?Driver $driver = null): Collection
    {
        return Vehicle::with(['type', 'activeAssignment.driver'])->orderBy('registration_number')->get()
            ->map(function (Vehicle $v) use ($delivery, $driver) {
                $load = $this->availability->activeLoad($v, $delivery?->id);
                $reasons = [];
                if ($delivery && $driver) {
                    $reasons = $this->eligibility($delivery, $driver, $v);
                } else {
                    try {
                        $this->vehicleAssignments->assertVehicleUsable($v);
                    } catch (DomainException $e) {
                        $reasons[] = $e->getMessage();
                    }
                    if ($delivery && (float) $v->capacity > 0 && $load + (float) $delivery->total_weight > (float) $v->capacity) {
                        $reasons[] = 'Insufficient capacity';
                    }
                }

                return ['vehicle' => $v, 'load' => round($load, 2), 'free_capacity' => max(0, round((float) $v->capacity - $load, 2)), 'eligible' => $reasons === [], 'reasons' => $reasons];
            });
    }

    private function announce(DeliveryAssignment $assignment, string $action): void
    {
        $delivery = $assignment->delivery;
        $company = $delivery->company;
        event(new DriverAssignmentUpdated($company->id, $company->public_id, [
            'action' => $action,
            'assignment_id' => $assignment->id,
            'delivery_id' => $delivery->id,
            'reference' => $delivery->reference_number,
            'driver_id' => $assignment->driver_id,
            'vehicle_id' => $assignment->vehicle_id,
            'status' => $assignment->status->value,
        ], ["driver.{$assignment->driver_id}"]));

        if (in_array($action, ['assigned', 'reassigned'], true)) {
            $this->notifyDriver($assignment->driver, $assignment, $action);
        } else {
            // Tell dispatch the driver's answer.
            Notify::companyUsersWith($company, 'deliveries.assign',
                new AssignmentNotification($company->id, $delivery->id, $delivery->reference_number, $action, $assignment->driver?->fullName(), $assignment->response_reason));
        }
    }

    private function notifyDriver(?Driver $driver, DeliveryAssignment $assignment, string $action): void
    {
        $driver?->user?->notify(new AssignmentNotification(
            $assignment->company_id, $assignment->delivery_id, $assignment->delivery->reference_number, $action, $driver->fullName(), null,
        ));
    }
}

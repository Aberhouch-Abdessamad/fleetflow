<?php

namespace App\Services\Fleet;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Services\ActivityLogger;
use App\Services\Tenant\CompanySettings;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Driver ↔ vehicle assignment with full history and conflict prevention. */
class VehicleAssignmentService
{
    public function __construct(
        private readonly VehicleAvailabilityService $availability,
        private readonly CompanySettings $settings,
        private readonly ActivityLogger $logger,
    ) {}

    public function assign(Vehicle $vehicle, Driver $driver, User $by, ?string $notes = null): VehicleAssignment
    {
        try {
            return DB::transaction(function () use ($vehicle, $driver, $by, $notes) {
                $vehicle = Vehicle::whereKey($vehicle->id)->lockForUpdate()->firstOrFail();
                $driver = Driver::whereKey($driver->id)->lockForUpdate()->firstOrFail();

                $this->assertVehicleUsable($vehicle);
                if ($driver->status !== DriverStatus::Active) {
                    throw new DomainException('Only active drivers can be assigned a vehicle.', 'driver_inactive');
                }
                if (! $driver->licenseValidOn(now())) {
                    throw new DomainException("{$driver->fullName()}'s licence has expired.", 'license_expired');
                }
                if (! $driver->licenseCovers($vehicle->type?->required_license)) {
                    throw new DomainException("Licence category {$driver->license_type} does not cover this vehicle (requires {$vehicle->type->required_license}).", 'license_category');
                }

                $current = $vehicle->activeAssignment;
                if ($current && $current->driver_id === $driver->id) {
                    return $current;
                }
                $exclusive = (bool) $this->settings->get($vehicle->company, 'exclusive_vehicle_assignment');
                if ($current && $exclusive) {
                    throw new DomainException('This vehicle is already assigned to '.$current->driver->fullName().'. Unassign it first.', 'vehicle_assigned');
                }
                if (Delivery::where('assigned_driver_id', $driver->id)->whereIn('status', DeliveryStatus::activeValues())
                    ->whereNotNull('assigned_vehicle_id')->where('assigned_vehicle_id', '!=', $vehicle->id)->exists()) {
                    throw new DomainException('The driver has active deliveries on another vehicle.', 'driver_busy');
                }

                // A driver holds one vehicle at a time: close their previous assignment.
                $driver->activeVehicleAssignment?->forceFill([
                    'unassigned_at' => now(), 'unassigned_by' => $by->id, 'active_vehicle_id' => null, 'active_driver_id' => null,
                ])->save();

                $assignment = VehicleAssignment::create([
                    'vehicle_id' => $vehicle->id,
                    'driver_id' => $driver->id,
                    'assigned_at' => now(),
                    'assigned_by' => $by->id,
                    'notes' => $notes,
                    'active_vehicle_id' => $exclusive ? $vehicle->id : null,
                    'active_driver_id' => $driver->id,
                ]);
                $this->availability->refresh($vehicle);
                $this->logger->log('vehicle.assigned', $vehicle, ['driver_id' => $driver->id]);

                return $assignment;
            });
        } catch (QueryException $e) {
            // Unique active_* index tripped by a concurrent request.
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                throw new DomainException('Another assignment for this vehicle or driver was just made. Refresh and try again.', 'assignment_conflict', 409);
            }
            throw $e;
        }
    }

    public function unassign(VehicleAssignment $assignment, User $by): VehicleAssignment
    {
        if ($assignment->unassigned_at) {
            return $assignment;
        }
        if (Delivery::where('assigned_driver_id', $assignment->driver_id)->where('assigned_vehicle_id', $assignment->vehicle_id)
            ->whereIn('status', [DeliveryStatus::PickedUp->value, DeliveryStatus::InTransit->value, DeliveryStatus::Arrived->value])->exists()) {
            throw new DomainException('The driver is on the road with this vehicle.', 'vehicle_on_road');
        }
        $assignment->forceFill([
            'unassigned_at' => now(), 'unassigned_by' => $by->id, 'active_vehicle_id' => null, 'active_driver_id' => null,
        ])->save();
        $this->availability->refreshId($assignment->vehicle_id);
        $this->logger->log('vehicle.unassigned', $assignment->vehicle, ['driver_id' => $assignment->driver_id]);

        return $assignment;
    }

    public function assertVehicleUsable(Vehicle $vehicle): void
    {
        $msg = match ($vehicle->status) {
            VehicleStatus::UnderMaintenance => 'This vehicle is under maintenance.',
            VehicleStatus::OutOfService => 'This vehicle is out of service.',
            VehicleStatus::Inactive => 'This vehicle is inactive.',
            default => null,
        };
        if ($msg) {
            throw new DomainException($msg, 'vehicle_unavailable');
        }
    }
}

<?php

namespace App\Services\Fleet;

use App\Enums\DeliveryStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Models\Delivery;
use App\Models\MaintenanceRecord;
use App\Models\Vehicle;

/**
 * Derives a vehicle's operational status from facts so it can never drift:
 * maintenance in progress → under_maintenance; a delivery on the road → on_delivery;
 * an active driver assignment or active deliveries → assigned; else available.
 * Manually set out_of_service / inactive are respected until changed by a manager.
 */
class VehicleAvailabilityService
{
    public const MANUAL = [VehicleStatus::OutOfService, VehicleStatus::Inactive];

    public function refresh(Vehicle $vehicle): Vehicle
    {
        if (in_array($vehicle->status, self::MANUAL, true)) {
            return $vehicle;
        }

        $status = match (true) {
            MaintenanceRecord::where('vehicle_id', $vehicle->id)->where('status', MaintenanceStatus::InProgress)->exists() => VehicleStatus::UnderMaintenance,
            Delivery::where('assigned_vehicle_id', $vehicle->id)->whereIn('status', [DeliveryStatus::PickedUp, DeliveryStatus::InTransit, DeliveryStatus::Arrived])->exists() => VehicleStatus::OnDelivery,
            $vehicle->activeAssignment()->exists()
                || Delivery::where('assigned_vehicle_id', $vehicle->id)->whereIn('status', DeliveryStatus::activeValues())->exists() => VehicleStatus::Assigned,
            default => VehicleStatus::Available,
        };

        if ($vehicle->status !== $status) {
            $vehicle->forceFill(['status' => $status])->save();
        }

        return $vehicle;
    }

    public function refreshId(?int $vehicleId): void
    {
        if ($vehicleId && ($v = Vehicle::find($vehicleId))) {
            $this->refresh($v);
        }
    }

    /** Current load (kg) carried by active deliveries on this vehicle, optionally excluding one delivery. */
    public function activeLoad(Vehicle $vehicle, ?int $exceptDeliveryId = null): float
    {
        return (float) Delivery::where('assigned_vehicle_id', $vehicle->id)
            ->whereIn('status', DeliveryStatus::activeValues())
            ->when($exceptDeliveryId, fn ($q) => $q->whereKeyNot($exceptDeliveryId))
            ->sum('total_weight');
    }
}

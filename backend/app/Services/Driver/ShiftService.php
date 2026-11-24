<?php

namespace App\Services\Driver;

use App\Enums\DriverAvailability;
use App\Exceptions\DomainException;
use App\Models\Driver;
use App\Models\DriverShift;
use Illuminate\Support\Facades\DB;

/** On-duty / off-duty shifts → working hours. Location sharing requires an open shift or active delivery. */
class ShiftService
{
    public function __construct(private readonly DriverService $drivers) {}

    public function start(Driver $driver): DriverShift
    {
        if ($driver->status->value !== 'active') {
            throw new DomainException('Your driver profile is not active.', 'driver_inactive', 403);
        }

        return DB::transaction(function () use ($driver) {
            $open = DriverShift::where('driver_id', $driver->id)->whereNull('ended_at')->lockForUpdate()->first();
            if ($open) {
                return $open;
            }
            $shift = DriverShift::create([
                'driver_id' => $driver->id,
                'vehicle_id' => $driver->activeVehicleAssignment?->vehicle_id,
                'started_at' => now(),
            ]);
            $this->drivers->setAvailability($driver, DriverAvailability::OnDuty, $driver->user, reason: 'Shift started');

            return $shift;
        });
    }

    public function end(Driver $driver): ?DriverShift
    {
        $shift = DriverShift::where('driver_id', $driver->id)->whereNull('ended_at')->first();
        if (! $shift) {
            return null;
        }
        if ($this->drivers->activeDeliveries($driver) > 0 && $driver->deliveries()->whereIn('status', ['picked_up', 'in_transit', 'arrived'])->exists()) {
            throw new DomainException('Finish the delivery you are carrying before ending your shift.', 'delivery_in_progress');
        }
        $shift->forceFill(['ended_at' => now(), 'duration_minutes' => (int) $shift->started_at->diffInMinutes(now())])->save();
        $this->drivers->setAvailability($driver->fresh(), DriverAvailability::Available, $driver->user, reason: 'Shift ended');

        return $shift;
    }
}

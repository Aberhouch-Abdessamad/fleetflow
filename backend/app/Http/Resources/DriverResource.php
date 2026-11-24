<?php

namespace App\Http\Resources;

use App\Models\Driver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Driver
 * Private fields (ID number, address, DOB, emergency contact, licence number) are only
 * included for users with drivers.view_private or the driver themself.
 */
class DriverResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $private = $user && ($user->can('drivers.view_private') || $user->id === $this->user_id);

        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'name' => $this->fullName(),
            'email' => $this->email,
            'phone' => $this->phone,
            'photo_url' => Concerns::file($this->photo_path, 'driver-photo', $this->id),
            'license_type' => $this->license_type,
            'license_expiry_date' => Concerns::date($this->license_expiry_date),
            'license_valid' => $this->licenseValidOn(now()),
            'employment_date' => Concerns::date($this->employment_date),
            'status' => $this->status->value,
            'availability' => $this->availability->value,
            'has_account' => $this->user_id !== null,
            'vehicle' => $this->whenLoaded('activeVehicleAssignment', fn () => $this->activeVehicleAssignment?->vehicle ? [
                'id' => $this->activeVehicleAssignment->vehicle->id,
                'registration_number' => $this->activeVehicleAssignment->vehicle->registration_number,
                'label' => $this->activeVehicleAssignment->vehicle->label(),
                'assignment_id' => $this->activeVehicleAssignment->id,
            ] : null),
            'on_shift' => $this->whenLoaded('openShift', fn () => $this->openShift !== null),
            'shift_started_at' => $this->whenLoaded('openShift', fn () => Concerns::dt($this->openShift?->started_at)),
            'active_deliveries_count' => $this->whenCounted('active_deliveries'),
            $this->mergeWhen($private, fn () => [
                'license_number' => $this->license_number,
                'national_id' => $this->national_id,
                'address' => $this->address,
                'date_of_birth' => Concerns::date($this->date_of_birth),
                'emergency_contact' => $this->emergency_contact,
                'emergency_phone' => $this->emergency_phone,
                'notes' => $this->notes,
            ]),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}

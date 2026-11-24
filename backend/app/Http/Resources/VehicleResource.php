<?php

namespace App\Http\Resources;

use App\Models\Vehicle;
use App\Services\Tracking\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Vehicle */
class VehicleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'registration_number' => $this->registration_number,
            'type' => $this->whenLoaded('type', fn () => new VehicleTypeResource($this->type)),
            'vehicle_type_id' => $this->vehicle_type_id,
            'make' => $this->make,
            'model' => $this->model,
            'year' => $this->year,
            'color' => $this->color,
            'vin' => $this->vin,
            'fuel_type' => $this->fuel_type?->value,
            'capacity' => $this->capacity,
            'capacity_unit' => $this->capacity_unit,
            'odometer' => $this->odometer,
            'status' => $this->status->value,
            'image_url' => Concerns::file($this->image_path, 'vehicle-image', $this->id),
            'insurance_provider' => $this->insurance_provider,
            'insurance_policy_number' => $this->insurance_policy_number,
            'insurance_expiry_date' => Concerns::date($this->insurance_expiry_date),
            'registration_expiry_date' => Concerns::date($this->registration_expiry_date),
            'last_service_date' => Concerns::date($this->last_service_date),
            'next_service_date' => Concerns::date($this->next_service_date),
            'next_service_odometer' => $this->next_service_odometer,
            'notes' => $this->notes,
            'current_driver' => $this->whenLoaded('activeAssignment', fn () => $this->activeAssignment?->driver ? [
                'id' => $this->activeAssignment->driver->id,
                'name' => $this->activeAssignment->driver->fullName(),
                'assignment_id' => $this->activeAssignment->id,
            ] : null),
            'location' => $this->last_located_at ? [
                'latitude' => $this->last_latitude,
                'longitude' => $this->last_longitude,
                'speed' => $this->last_speed,
                'heading' => $this->last_heading,
                'recorded_at' => Concerns::dt($this->last_located_at),
                'freshness' => LocationService::freshness($this->last_located_at),
            ] : null,
            'archived' => $this->deleted_at !== null,
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}

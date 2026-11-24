<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number, 'label' => $this->vehicle->label()]),
            'driver' => $this->whenLoaded('driver', fn () => ['id' => $this->driver->id, 'name' => $this->driver->fullName()]),
            'assigned_at' => Concerns::dt($this->assigned_at),
            'unassigned_at' => Concerns::dt($this->unassigned_at),
            'assigned_by' => $this->whenLoaded('assigner', fn () => $this->assigner?->name),
            'notes' => $this->notes,
            'active' => $this->unassigned_at === null,
        ];
    }
}

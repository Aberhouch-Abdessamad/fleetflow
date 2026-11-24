<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FuelRecordResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number]),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->fullName()] : null),
            'fuel_type' => $this->fuel_type,
            'quantity' => $this->quantity,
            'unit_price' => $this->unit_price,
            'total_cost' => $this->total_cost,
            'odometer' => $this->odometer,
            'full_tank' => $this->full_tank,
            'station_name' => $this->station_name,
            'receipt_url' => Concerns::file($this->receipt_path, 'fuel-receipt', $this->id),
            'recorded_at' => Concerns::dt($this->recorded_at),
        ];
    }
}

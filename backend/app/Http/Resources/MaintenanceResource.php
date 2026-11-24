<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'vehicle' => $this->whenLoaded('vehicle', fn () => ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number, 'label' => $this->vehicle->label()]),
            'vehicle_id' => $this->vehicle_id,
            'maintenance_type' => $this->maintenance_type->value,
            'title' => $this->title,
            'description' => $this->description,
            'service_provider' => $this->service_provider,
            'scheduled_date' => Concerns::date($this->scheduled_date),
            'completed_date' => Concerns::date($this->completed_date),
            'odometer' => $this->odometer,
            'cost' => $this->cost,
            'status' => $this->status->value,
            'overdue' => $this->status->value === 'scheduled' && $this->scheduled_date?->lt(now()->startOfDay()),
            'next_service_date' => Concerns::date($this->next_service_date),
            'next_service_odometer' => $this->next_service_odometer,
            'invoice_url' => Concerns::file($this->invoice_path, 'maintenance-invoice', $this->id),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}

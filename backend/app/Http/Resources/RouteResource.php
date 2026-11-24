<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RouteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $stops = $this->relationLoaded('stops') ? $this->stops : null;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'reference_number' => $this->reference_number,
            'route_date' => Concerns::date($this->route_date),
            'status' => $this->status->value,
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->fullName()] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? ['id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number, 'capacity' => $this->vehicle->capacity] : null),
            'driver_id' => $this->driver_id,
            'vehicle_id' => $this->vehicle_id,
            'start' => ['address' => $this->start_location, 'latitude' => $this->start_latitude, 'longitude' => $this->start_longitude],
            'end' => ['address' => $this->end_location, 'latitude' => $this->end_latitude, 'longitude' => $this->end_longitude],
            'planned_distance' => $this->planned_distance,
            'actual_distance' => $this->actual_distance,
            'estimated_duration' => $this->estimated_duration,
            'actual_duration' => $this->actual_duration,
            'distance_source' => $this->distance_source,
            'geometry' => $this->geometry,
            'started_at' => Concerns::dt($this->started_at),
            'completed_at' => Concerns::dt($this->completed_at),
            'stops_count' => $stops?->count() ?? $this->whenCounted('stops'),
            'completed_stops' => $stops?->where('status', 'completed')->count(),
            'total_weight' => $stops ? round($stops->sum(fn ($s) => (float) ($s->delivery?->total_weight ?? 0)), 2) : null,
            'stops' => $this->whenLoaded('stops', fn () => $this->stops->map(fn ($s) => [
                'id' => $s->id, 'stop_order' => $s->stop_order, 'stop_type' => $s->stop_type, 'address' => $s->address,
                'latitude' => $s->latitude, 'longitude' => $s->longitude, 'priority' => $s->priority, 'status' => $s->status,
                'estimated_arrival' => Concerns::dt($s->estimated_arrival), 'actual_arrival' => Concerns::dt($s->actual_arrival), 'completed_at' => Concerns::dt($s->completed_at),
                'delivery' => $s->delivery ? ['id' => $s->delivery->id, 'reference_number' => $s->delivery->reference_number, 'recipient' => $s->delivery->recipient_name, 'status' => $s->delivery->status->value, 'weight' => $s->delivery->total_weight, 'scheduled_delivery_at' => Concerns::dt($s->delivery->scheduled_delivery_at)] : null,
            ])),
            'optimizations' => $this->whenLoaded('optimizationLogs', fn () => $this->optimizationLogs->map(fn ($l) => [
                'provider' => $l->provider, 'distance_before' => $l->distance_before, 'distance_after' => $l->distance_after,
                'duration_before' => $l->duration_before, 'duration_after' => $l->duration_after, 'at' => Concerns::dt($l->optimized_at),
            ])),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}

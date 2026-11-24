<?php

namespace App\Http\Resources;

use App\Models\Delivery;
use App\Services\Tracking\LocationService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Delivery */
class DeliveryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'reference_number' => $this->reference_number,
            'external_source' => $this->external_source,
            'external_reference' => $this->external_reference,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'customer' => $this->whenLoaded('customer', fn () => $this->customer ? ['id' => $this->customer->id, 'name' => $this->customer->name, 'phone' => $this->customer->phone, 'email' => $this->customer->email] : null),
            'customer_id' => $this->customer_id,
            'pickup' => [
                'address' => $this->pickup_address, 'latitude' => $this->pickup_latitude, 'longitude' => $this->pickup_longitude,
                'contact_name' => $this->pickup_contact_name, 'contact_phone' => $this->pickup_contact_phone,
            ],
            'dropoff' => [
                'address' => $this->delivery_address, 'latitude' => $this->delivery_latitude, 'longitude' => $this->delivery_longitude, 'city' => $this->delivery_city,
            ],
            'recipient' => ['name' => $this->recipient_name, 'phone' => $this->recipient_phone, 'email' => $this->recipient_email],
            'description' => $this->description,
            'scheduled_pickup_at' => Concerns::dt($this->scheduled_pickup_at),
            'scheduled_delivery_at' => Concerns::dt($this->scheduled_delivery_at),
            'actual_pickup_at' => Concerns::dt($this->actual_pickup_at),
            'actual_delivery_at' => Concerns::dt($this->actual_delivery_at),
            'assigned_at' => Concerns::dt($this->assigned_at),
            'accepted_at' => Concerns::dt($this->accepted_at),
            'driver' => $this->whenLoaded('driver', fn () => $this->driver ? ['id' => $this->driver->id, 'name' => $this->driver->fullName(), 'phone' => $this->driver->phone] : null),
            'vehicle' => $this->whenLoaded('vehicle', fn () => $this->vehicle ? [
                'id' => $this->vehicle->id, 'registration_number' => $this->vehicle->registration_number, 'label' => $this->vehicle->label(),
                'location' => $this->vehicle->last_located_at ? ['latitude' => $this->vehicle->last_latitude, 'longitude' => $this->vehicle->last_longitude, 'recorded_at' => Concerns::dt($this->vehicle->last_located_at), 'freshness' => LocationService::freshness($this->vehicle->last_located_at)] : null,
            ] : null),
            'route_id' => $this->route_id,
            'total_weight' => $this->total_weight,
            'distance_km' => $this->distance_km,
            'delivery_fee' => $this->delivery_fee,
            'cod_amount' => $this->cod_amount,
            'cod_collected_amount' => $this->cod_collected_amount,
            'requires_proof' => $this->requires_proof,
            'requires_otp' => $this->otp_hash !== null,
            'delivery_notes' => $this->delivery_notes,
            'failure_reason' => $this->failure_reason,
            'cancellation_reason' => $this->cancellation_reason,
            'customer_rating' => $this->customer_rating,
            'rating_comment' => $this->rating_comment,
            'on_time' => $this->isOnTime(),
            'is_late' => $this->status->isActive() && $this->scheduled_delivery_at?->isPast(),
            'allowed_transitions' => array_map(fn ($s) => $s->value, $this->status->allowedTransitions()),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($i) => [
                'id' => $i->id, 'description' => $i->description, 'quantity' => $i->quantity, 'weight' => $i->weight, 'dimensions' => $i->dimensions, 'notes' => $i->notes,
            ])),
            'timeline' => $this->whenLoaded('statusHistory', fn () => $this->statusHistory->map(fn ($h) => [
                'id' => $h->id, 'from' => $h->previous_status, 'to' => $h->new_status, 'by' => $h->user?->name, 'source' => $h->source,
                'notes' => $h->notes, 'latitude' => $h->latitude, 'longitude' => $h->longitude, 'at' => Concerns::dt($h->changed_at),
            ])),
            'assignments' => $this->whenLoaded('assignments', fn () => $this->assignments->map(fn ($a) => [
                'id' => $a->id, 'driver' => $a->driver?->fullName(), 'driver_id' => $a->driver_id, 'vehicle' => $a->vehicle?->registration_number,
                'status' => $a->status->value, 'assigned_by' => $a->assigner?->name, 'reason' => $a->response_reason,
                'created_at' => Concerns::dt($a->created_at), 'responded_at' => Concerns::dt($a->responded_at),
            ])),
            'current_assignment' => $this->whenLoaded('currentAssignment', fn () => $this->currentAssignment ? ['id' => $this->currentAssignment->id, 'status' => $this->currentAssignment->status->value] : null),
            'proofs' => $this->whenLoaded('proofs', fn () => $this->proofs->map(fn ($p) => [
                'id' => $p->id, 'type' => $p->proof_type->value, 'recipient_name' => $p->recipient_name, 'notes' => $p->notes,
                'photo_url' => Concerns::file($p->file_path, 'proof-photo', $p->id),
                'signature_url' => Concerns::file($p->recipient_signature_path, 'proof-signature', $p->id),
                'latitude' => $p->latitude, 'longitude' => $p->longitude, 'created_at' => Concerns::dt($p->created_at),
            ])),
            'invoiced' => $this->whenLoaded('invoiceItem', fn () => $this->invoiceItem !== null),
            'tracking_url' => $request->user()?->can('deliveries.view') ? rtrim((string) config('app.frontend_url'), '/').'/track/'.$this->tracking_token : null,
            'created_at' => Concerns::dt($this->created_at),
            'updated_at' => Concerns::dt($this->updated_at),
        ];
    }
}

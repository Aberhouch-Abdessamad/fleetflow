<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'customer_type' => $this->customer_type,
            'tax_number' => $this->tax_number,
            'notes' => $this->notes,
            'addresses' => $this->whenLoaded('addresses', fn () => $this->addresses->map(fn ($a) => [
                'id' => $a->id, 'label' => $a->label, 'address_type' => $a->address_type, 'address' => $a->address, 'city' => $a->city,
                'postal_code' => $a->postal_code, 'latitude' => $a->latitude, 'longitude' => $a->longitude,
                'contact_name' => $a->contact_name, 'contact_phone' => $a->contact_phone, 'is_default' => $a->is_default,
            ])),
            'deliveries_count' => $this->whenCounted('deliveries'),
            'created_at' => Concerns::dt($this->created_at),
        ];
    }
}

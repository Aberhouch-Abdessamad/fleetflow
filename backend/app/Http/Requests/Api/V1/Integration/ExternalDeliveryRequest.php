<?php

namespace App\Http\Requests\Api\V1\Integration;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExternalDeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'external_reference' => ['required', 'string', 'max:120'],
            'source' => ['nullable', 'string', 'max:40', 'alpha_dash'],
            'customer' => ['nullable', 'array'],
            'customer.name' => ['required_with:customer', 'string', 'max:150'],
            'customer.email' => ['nullable', 'email'],
            'customer.phone' => ['nullable', 'string', 'max:32'],
            'pickup' => ['required', 'array'],
            'pickup.address' => ['required', 'string', 'max:255'],
            'pickup.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'pickup.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'pickup.contact_name' => ['nullable', 'string', 'max:120'],
            'pickup.contact_phone' => ['nullable', 'string', 'max:32'],
            'dropoff' => ['required', 'array'],
            'dropoff.address' => ['required', 'string', 'max:255'],
            'dropoff.city' => ['nullable', 'string', 'max:120'],
            'dropoff.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'dropoff.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'recipient' => ['required', 'array'],
            'recipient.name' => ['required', 'string', 'max:120'],
            'recipient.phone' => ['nullable', 'string', 'max:32'],
            'recipient.email' => ['nullable', 'email'],
            'description' => ['nullable', 'string', 'max:255'],
            'scheduled_pickup_at' => ['nullable', 'date'],
            'scheduled_delivery_at' => ['nullable', 'date'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'cod_amount' => ['nullable', 'numeric', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['nullable', 'array', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0'],
        ];
    }
}

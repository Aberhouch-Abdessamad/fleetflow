<?php

namespace App\Http\Requests\Api\V1\Delivery;

use App\Enums\DeliveryPriority;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeliveryRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'customer_id' => ['nullable', Rule::exists('customers', 'id')->where('company_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'pickup_address' => [$req, 'string', 'max:255'],
            'pickup_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:pickup_longitude'],
            'pickup_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:pickup_latitude'],
            'pickup_contact_name' => ['nullable', 'string', 'max:120'],
            'pickup_contact_phone' => ['nullable', 'string', 'max:32'],
            'delivery_address' => [$req, 'string', 'max:255'],
            'delivery_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:delivery_longitude'],
            'delivery_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:delivery_latitude'],
            'delivery_city' => ['nullable', 'string', 'max:120'],
            'recipient_name' => [$req, 'string', 'max:120'],
            'recipient_phone' => ['nullable', 'string', 'max:32'],
            'recipient_email' => ['nullable', 'email', 'max:190'],
            'description' => ['nullable', 'string', 'max:255'],
            'scheduled_pickup_at' => ['nullable', 'date'],
            'scheduled_delivery_at' => ['nullable', 'date', 'after_or_equal:scheduled_pickup_at'],
            'priority' => ['sometimes', Rule::enum(DeliveryPriority::class)],
            'delivery_fee' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'cod_amount' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'requires_proof' => ['sometimes', 'boolean'],
            'require_otp' => ['sometimes', 'boolean'],
            'delivery_notes' => ['nullable', 'string', 'max:2000'],
            'as_draft' => ['sometimes', 'boolean'],
            'publish' => ['sometimes', 'boolean'],
            'items' => ['sometimes', 'array', 'max:100'],
            'items.*.description' => ['required', 'string', 'max:255'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.weight' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'items.*.dimensions' => ['nullable', 'string', 'max:60'],
            'items.*.notes' => ['nullable', 'string', 'max:255'],
        ];
    }
}

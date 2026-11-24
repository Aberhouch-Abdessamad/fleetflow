<?php

namespace App\Http\Requests\Api\V1\Route;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RouteRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';
        $c = app(TenantContext::class)->id();

        return [
            'name' => [$req, 'string', 'max:150'],
            'route_date' => [$req, 'date'],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where('company_id', $c)],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'start_location' => ['nullable', 'string', 'max:255'],
            'start_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:start_longitude'],
            'start_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:start_latitude'],
            'end_location' => ['nullable', 'string', 'max:255'],
            'end_latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:end_longitude'],
            'end_longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:end_latitude'],
            'delivery_ids' => ['sometimes', 'array', 'max:100'],
            'delivery_ids.*' => ['integer', Rule::exists('deliveries', 'id')->where('company_id', $c)],
            'stop_order' => ['sometimes', 'array'],
            'stop_order.*' => ['integer'],
        ];
    }
}

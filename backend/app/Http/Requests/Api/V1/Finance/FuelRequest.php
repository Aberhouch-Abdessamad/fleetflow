<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\FuelType;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** total_cost is never accepted — it is computed. */
class FuelRequest extends FormRequest
{
    public function rules(): array
    {
        $c = app(TenantContext::class)->id();

        return [
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $c)->whereNull('deleted_at')],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where('company_id', $c)],
            'fuel_type' => ['required', Rule::enum(FuelType::class)],
            'quantity' => ['required', 'numeric', 'min:0.1', 'max:5000'],
            'unit_price' => ['required', 'numeric', 'min:0.001', 'max:100000'],
            'odometer' => ['required', 'integer', 'min:0'],
            'full_tank' => ['sometimes', 'boolean'],
            'station_name' => ['nullable', 'string', 'max:120'],
            'recorded_at' => ['required', 'date', 'before_or_equal:now'],
            'receipt' => ['nullable', ...Uploads::DOCUMENT_RULES],
            'total_cost' => ['prohibited'],
        ];
    }
}

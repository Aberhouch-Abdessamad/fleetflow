<?php

namespace App\Http\Requests\Api\V1\Fleet;

use App\Enums\FuelType;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class VehicleRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('vehicle') ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->id();
        $id = $this->route('vehicle')?->id;
        $unique = fn ($col) => Rule::unique('vehicles', $col)->where('company_id', $company)->ignore($id);

        return [
            'vehicle_type_id' => [$req, Rule::exists('vehicle_types', 'id')->where(fn ($q) => $q->whereNull('company_id')->orWhere('company_id', $company))],
            'registration_number' => [$req, 'string', 'max:32', $unique('registration_number')],
            'make' => [$req, 'string', 'max:60'],
            'model' => [$req, 'string', 'max:60'],
            'year' => ['nullable', 'integer', 'min:1950', 'max:'.(now()->year + 1)],
            'color' => ['nullable', 'string', 'max:30'],
            'vin' => ['nullable', 'string', 'max:32', $unique('vin')],
            'fuel_type' => [$req, Rule::enum(FuelType::class)],
            'capacity' => [$req, 'numeric', 'min:0', 'max:100000'],
            'capacity_unit' => ['sometimes', Rule::in(['kg', 't', 'm3', 'pallets'])],
            'odometer' => ['sometimes', 'integer', 'min:0', 'max:5000000'],
            'insurance_provider' => ['nullable', 'string', 'max:100'],
            'insurance_policy_number' => ['nullable', 'string', 'max:64'],
            'insurance_expiry_date' => ['nullable', 'date'],
            'registration_expiry_date' => ['nullable', 'date'],
            'last_service_date' => ['nullable', 'date', 'before_or_equal:today'],
            'next_service_date' => ['nullable', 'date'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'image' => ['nullable', ...Uploads::IMAGE_RULES],
        ];
    }

    public function vehicleData(): array
    {
        $data = collect($this->validated())->except('image')->all();
        if (isset($data['registration_number'])) {
            $data['registration_number'] = strtoupper(trim($data['registration_number']));
        }
        if (isset($data['vin'])) {
            $data['vin'] = strtoupper(trim($data['vin']));
        }

        return $data;
    }
}

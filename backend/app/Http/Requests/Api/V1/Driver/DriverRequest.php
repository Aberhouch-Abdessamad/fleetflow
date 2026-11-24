<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DriverRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('driver') ? 'sometimes' : 'required';
        $company = app(TenantContext::class)->id();

        return [
            'first_name' => [$req, 'string', 'max:80'],
            'last_name' => [$req, 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => [$req, 'string', 'max:32'],
            'license_number' => [$req, 'string', 'max:64', Rule::unique('drivers')->where('company_id', $company)->ignore($this->route('driver')?->id)],
            'license_type' => [$req, Rule::in(['A', 'B', 'C', 'CE', 'D'])],
            'license_expiry_date' => [$req, 'date'],
            'employment_date' => ['nullable', 'date', 'before_or_equal:today'],
            'emergency_contact' => ['nullable', 'string', 'max:120'],
            'emergency_phone' => ['nullable', 'string', 'max:32'],
            'national_id' => ['nullable', 'string', 'max:64'],
            'address' => ['nullable', 'string', 'max:255'],
            'date_of_birth' => ['nullable', 'date', 'before:-16 years'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'photo' => ['nullable', ...Uploads::IMAGE_RULES],
        ];
    }

    public function driverData(): array
    {
        return collect($this->validated())->except('photo')->all();
    }
}

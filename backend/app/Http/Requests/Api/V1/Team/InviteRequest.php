<?php

namespace App\Http\Requests\Api\V1\Team;

use App\Enums\CompanyRole;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InviteRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email', 'max:190'],
            'role' => ['required', Rule::enum(CompanyRole::class)->except([CompanyRole::Customer])],
            'driver_id' => ['nullable', 'required_if:role,driver', Rule::exists('drivers', 'id')->where('company_id', app(TenantContext::class)->id())],
        ];
    }

    public function messages(): array
    {
        return ['driver_id.required_if' => 'Select the driver profile this account belongs to.'];
    }
}

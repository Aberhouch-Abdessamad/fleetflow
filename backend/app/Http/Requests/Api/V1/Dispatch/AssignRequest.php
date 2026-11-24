<?php

namespace App\Http\Requests\Api\V1\Dispatch;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignRequest extends FormRequest
{
    public function rules(): array
    {
        $c = app(TenantContext::class)->id();

        return [
            'delivery_id' => [$this->route('delivery') ? 'prohibited' : 'required', Rule::exists('deliveries', 'id')->where('company_id', $c)->whereNull('deleted_at')],
            'driver_id' => ['required', Rule::exists('drivers', 'id')->where('company_id', $c)->whereNull('deleted_at')],
            'vehicle_id' => ['required', Rule::exists('vehicles', 'id')->where('company_id', $c)->whereNull('deleted_at')],
        ];
    }
}

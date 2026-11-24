<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('maintenance') ? 'sometimes' : 'required';

        return [
            'vehicle_id' => [$this->route('maintenance') ? 'prohibited' : 'required', Rule::exists('vehicles', 'id')->where('company_id', app(TenantContext::class)->id())->whereNull('deleted_at')],
            'maintenance_type' => [$req, Rule::enum(MaintenanceType::class)],
            'title' => [$req, 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:3000'],
            'service_provider' => ['nullable', 'string', 'max:120'],
            'scheduled_date' => ['nullable', 'date'],
            'completed_date' => ['nullable', 'date', 'before_or_equal:today'],
            'odometer' => ['nullable', 'integer', 'min:0'],
            'cost' => ['sometimes', 'numeric', 'min:0', 'max:9999999999'],
            'status' => ['sometimes', Rule::enum(MaintenanceStatus::class)],
            'next_service_date' => ['nullable', 'date'],
            'next_service_odometer' => ['nullable', 'integer', 'min:0'],
            'invoice' => ['nullable', ...Uploads::DOCUMENT_RULES],
        ];
    }
}

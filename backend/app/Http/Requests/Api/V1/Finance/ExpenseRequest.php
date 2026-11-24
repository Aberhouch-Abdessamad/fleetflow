<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Enums\ExpenseCategory;
use App\Support\Tenancy\TenantContext;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExpenseRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->route('expense') ? 'sometimes' : 'required';
        $c = app(TenantContext::class)->id();

        return [
            'category' => [$req, Rule::enum(ExpenseCategory::class)],
            'description' => [$req, 'string', 'max:255'],
            'amount' => [$req, 'numeric', 'min:0.01', 'max:9999999999'],
            'expense_date' => [$req, 'date', 'before_or_equal:today'],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('company_id', $c)],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where('company_id', $c)],
            'delivery_id' => ['nullable', Rule::exists('deliveries', 'id')->where('company_id', $c)],
            'receipt' => ['nullable', ...Uploads::DOCUMENT_RULES],
            'status' => ['prohibited'],
        ];
    }
}

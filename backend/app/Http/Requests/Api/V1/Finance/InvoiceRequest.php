<?php

namespace App\Http\Requests\Api\V1\Finance;

use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Only references and line inputs — subtotal/tax/total are always computed. */
class InvoiceRequest extends FormRequest
{
    public function rules(): array
    {
        $c = app(TenantContext::class)->id();

        return [
            'customer_id' => ['required', Rule::exists('customers', 'id')->where('company_id', $c)],
            'delivery_ids' => ['sometimes', 'array', 'max:500'],
            'delivery_ids.*' => ['integer', 'distinct'],
            'lines' => ['sometimes', 'array', 'max:100'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'min:0.01', 'max:100000'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'tax_rate' => ['sometimes', 'numeric', 'min:0', 'max:100'],
            'due_date' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'subtotal' => ['prohibited'], 'total_amount' => ['prohibited'], 'tax_amount' => ['prohibited'],
        ];
    }
}

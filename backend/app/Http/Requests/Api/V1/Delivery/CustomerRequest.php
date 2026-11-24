<?php

namespace App\Http\Requests\Api\V1\Delivery;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'customer_type' => ['sometimes', Rule::in(['business', 'individual'])],
            'tax_number' => ['nullable', 'string', 'max:64'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'addresses' => ['sometimes', 'array', 'max:20'],
            'addresses.*' => ['array'],
            ...collect(AddressRequest::base())->mapWithKeys(fn ($r, $k) => ["addresses.*.{$k}" => $r])->all(),
        ];
    }
}

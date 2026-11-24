<?php

namespace App\Http\Requests\Api\V1\Company;

use Illuminate\Foundation\Http\FormRequest;

/** Used for both create (POST) and update (PUT). company_id/status/owner are never accepted. */
class CompanyRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$required, 'string', 'min:2', 'max:150'],
            'registration_number' => ['nullable', 'string', 'max:64'],
            'contact_email' => [$required, 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:32'],
            'country' => [$required, 'string', 'size:2', 'alpha'],
            'city' => [$required, 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'timezone' => [$required, 'timezone:all'],
            'currency' => [$required, 'string', 'size:3', 'alpha'],
        ];
    }

    public function companyData(): array
    {
        $data = $this->validated();
        foreach (['country', 'currency'] as $k) {
            if (isset($data[$k])) {
                $data[$k] = strtoupper($data[$k]);
            }
        }

        return $data;
    }
}

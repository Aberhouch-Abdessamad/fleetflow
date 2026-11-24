<?php

namespace App\Http\Requests\Api\V1\Auth;

use Illuminate\Foundation\Http\FormRequest;

class LoginRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
            // Present for token-based clients (driver app, integrations); omitted by the SPA.
            'device_name' => ['nullable', 'string', 'max:100'],
        ];
    }
}

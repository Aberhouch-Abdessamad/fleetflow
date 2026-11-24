<?php

namespace App\Http\Requests\Api\V1\Delivery;

use App\Enums\ProofType;
use App\Support\Uploads;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProofRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'proof_type' => ['required', Rule::enum(ProofType::class)],
            'photo' => ['required_if:proof_type,photo', 'nullable', ...Uploads::IMAGE_RULES],
            'signature' => ['required_if:proof_type,signature', 'nullable', 'string', 'max:3000000'],
            'otp' => ['required_if:proof_type,otp', 'nullable', 'digits:6'],
            'recipient_name' => ['nullable', 'string', 'max:120'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }
}

<?php

namespace App\Http\Requests\Api\V1\Driver;

use App\Enums\DriverAvailability;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvailabilityRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'availability' => ['required', Rule::enum(DriverAvailability::class)],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }
}

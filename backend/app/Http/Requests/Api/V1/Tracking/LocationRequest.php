<?php

namespace App\Http\Requests\Api\V1\Tracking;

use Illuminate\Foundation\Http\FormRequest;

/** driver_id / vehicle_id are intentionally NOT accepted — they are derived server-side. */
class LocationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'speed' => ['nullable', 'numeric', 'min:0', 'max:400'],
            'heading' => ['nullable', 'numeric', 'min:0', 'max:360'],
            'accuracy' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'recorded_at' => ['required', 'date'],
            'driver_id' => ['prohibited'],
            'vehicle_id' => ['prohibited'],
            'company_id' => ['prohibited'],
        ];
    }
}

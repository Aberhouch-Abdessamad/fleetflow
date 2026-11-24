<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Company */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'name' => $this->name,
            'slug' => $this->slug,
            'logo_url' => $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null,
            'registration_number' => $this->registration_number,
            'contact_email' => $this->contact_email,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'address' => $this->address,
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'status' => $this->status->value,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}

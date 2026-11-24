<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class VehicleTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'required_license' => $this->required_license, 'default_capacity' => $this->default_capacity, 'capacity_unit' => $this->capacity_unit, 'is_custom' => $this->company_id !== null];
    }
}

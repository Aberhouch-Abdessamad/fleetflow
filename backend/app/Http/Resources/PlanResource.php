<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'name' => $this->name, 'code' => $this->code, 'description' => $this->description,
            'price_monthly' => $this->price_monthly, 'currency' => $this->currency, 'trial_days' => $this->trial_days,
            'max_vehicles' => $this->max_vehicles, 'max_drivers' => $this->max_drivers, 'max_users' => $this->max_users,
            'max_monthly_deliveries' => $this->max_monthly_deliveries, 'features' => $this->features ?? [],
            'is_active' => $this->is_active, 'is_default' => $this->is_default, 'sort_order' => $this->sort_order,
            'subscribers' => $this->whenCounted('active_subscriptions'),
        ];
    }
}

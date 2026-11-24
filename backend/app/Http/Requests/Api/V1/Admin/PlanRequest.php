<?php

namespace App\Http\Requests\Api\V1\Admin;

use App\Models\SubscriptionPlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PlanRequest extends FormRequest
{
    public function rules(): array
    {
        $req = $this->isMethod('post') ? 'required' : 'sometimes';

        return [
            'name' => [$req, 'string', 'max:100'],
            'code' => [$req, 'alpha_dash', 'max:50', Rule::unique('subscription_plans')->ignore($this->route('plan')?->id)],
            'description' => ['nullable', 'string', 'max:500'],
            'price_monthly' => [$req, 'numeric', 'min:0'],
            'currency' => [$req, 'string', 'size:3'],
            'trial_days' => ['sometimes', 'integer', 'min:0', 'max:90'],
            'max_vehicles' => ['nullable', 'integer', 'min:1'],
            'max_drivers' => ['nullable', 'integer', 'min:1'],
            'max_users' => ['nullable', 'integer', 'min:1'],
            'max_monthly_deliveries' => ['nullable', 'integer', 'min:1'],
            'features' => ['sometimes', 'array'],
            'features.*' => [Rule::in(SubscriptionPlan::FEATURES)],
            'is_active' => ['sometimes', 'boolean'],
            'is_default' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ];
    }
}

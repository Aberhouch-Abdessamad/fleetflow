<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    public const FEATURES = ['gps_tracking', 'route_planning', 'route_optimization', 'advanced_reports', 'api_integrations', 'priority_support'];

    protected $fillable = [
        'name', 'code', 'description', 'price_monthly', 'currency', 'trial_days', 'max_vehicles', 'max_drivers',
        'max_users', 'max_monthly_deliveries', 'features', 'is_active', 'is_default', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['features' => 'array', 'is_active' => 'boolean', 'is_default' => 'boolean', 'price_monthly' => 'decimal:2'];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class);
    }

    public function hasFeature(string $feature): bool
    {
        return in_array($feature, $this->features ?? [], true);
    }
}

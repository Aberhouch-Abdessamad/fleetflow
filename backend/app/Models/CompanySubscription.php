<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompanySubscription extends Model
{
    protected $fillable = ['company_id', 'subscription_plan_id', 'status', 'trial_ends_at', 'starts_at', 'ends_at', 'cancelled_at', 'changed_by', 'notes'];

    protected function casts(): array
    {
        return ['status' => SubscriptionStatus::class, 'trial_ends_at' => 'datetime', 'starts_at' => 'datetime', 'ends_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function isCurrent(): bool
    {
        return match ($this->status) {
            SubscriptionStatus::Trialing => $this->trial_ends_at === null || $this->trial_ends_at->isFuture(),
            SubscriptionStatus::Active => $this->ends_at === null || $this->ends_at->isFuture(),
            default => false,
        };
    }
}

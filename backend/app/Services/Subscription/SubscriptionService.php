<?php

namespace App\Services\Subscription;

use App\Enums\SubscriptionStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use Illuminate\Support\Facades\DB;

/**
 * Plan limits and feature gates, always enforced on the backend.
 * No payment flow is implemented here: plan changes are recorded by admins
 * (or by a future payment gateway adapter).
 */
class SubscriptionService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function current(Company $company): ?CompanySubscription
    {
        $sub = $company->relationLoaded('currentSubscription')
            ? $company->currentSubscription
            : $company->currentSubscription()->with('plan')->first();

        return $sub && $sub->isCurrent() ? $sub->loadMissing('plan') : null;
    }

    public function startTrial(Company $company, ?SubscriptionPlan $plan = null): ?CompanySubscription
    {
        $plan ??= SubscriptionPlan::where('is_default', true)->where('is_active', true)->first();
        if (! $plan) {
            return null;
        }

        return CompanySubscription::create([
            'company_id' => $company->id,
            'subscription_plan_id' => $plan->id,
            'status' => $plan->trial_days > 0 ? SubscriptionStatus::Trialing : SubscriptionStatus::Active,
            'trial_ends_at' => $plan->trial_days > 0 ? now()->addDays($plan->trial_days) : null,
            'starts_at' => now(),
        ]);
    }

    /** Upgrade / downgrade. Closes the current subscription and opens a new one (history kept). */
    public function changePlan(Company $company, SubscriptionPlan $plan, ?User $by, string $status = 'active', ?string $endsAt = null, ?string $notes = null): CompanySubscription
    {
        return DB::transaction(function () use ($company, $plan, $by, $status, $endsAt, $notes) {
            $company->subscriptions()->whereIn('status', ['trialing', 'active'])
                ->update(['status' => SubscriptionStatus::Cancelled->value, 'cancelled_at' => now(), 'ends_at' => now()]);

            $sub = CompanySubscription::create([
                'company_id' => $company->id,
                'subscription_plan_id' => $plan->id,
                'status' => $status,
                'trial_ends_at' => $status === 'trialing' ? now()->addDays(max(1, $plan->trial_days)) : null,
                'starts_at' => now(),
                'ends_at' => $endsAt,
                'changed_by' => $by?->id,
                'notes' => $notes,
            ]);
            $this->logger->log('subscription.changed', $company, ['plan' => $plan->code, 'status' => $status], $company->id);

            return $sub;
        });
    }

    public function plan(Company $company): ?SubscriptionPlan
    {
        return $this->current($company)?->plan;
    }

    public function hasFeature(Company $company, string $feature): bool
    {
        // Without any plans configured (fresh install) nothing is gated.
        if (! SubscriptionPlan::query()->exists()) {
            return true;
        }

        return (bool) $this->plan($company)?->hasFeature($feature);
    }

    public function assertFeature(Company $company, string $feature): void
    {
        if (! $this->hasFeature($company, $feature)) {
            throw new DomainException(
                'Your subscription plan does not include '.str_replace('_', ' ', $feature).'.',
                'plan_feature_unavailable',
                402
            );
        }
    }

    /** @param 'vehicles'|'drivers'|'users'|'monthly_deliveries' $resource */
    public function assertCanAdd(Company $company, string $resource, int $adding = 1): void
    {
        if (! SubscriptionPlan::query()->exists()) {
            return;
        }
        $sub = $this->current($company);
        if (! $sub) {
            throw new DomainException('Your subscription is not active. Contact your administrator.', 'subscription_inactive', 402);
        }
        $limit = $sub->plan->{'max_'.$resource};
        if ($limit === null) {
            return;
        }
        if ($this->usage($company, $resource) + $adding > $limit) {
            throw new DomainException(
                "Your {$sub->plan->name} plan allows up to {$limit} ".str_replace('_', ' ', $resource).'. Upgrade to add more.',
                'plan_limit_reached',
                402
            );
        }
    }

    public function usage(Company $company, string $resource): int
    {
        return match ($resource) {
            'vehicles' => Vehicle::withoutGlobalScope('company')->where('company_id', $company->id)->count(),
            'drivers' => Driver::withoutGlobalScope('company')->where('company_id', $company->id)->count(),
            'users' => $company->users()->wherePivot('status', 'active')->count(),
            'monthly_deliveries' => Delivery::withoutGlobalScope('company')->where('company_id', $company->id)
                ->where('created_at', '>=', now()->startOfMonth())->count(),
            default => 0,
        };
    }

    public function summary(Company $company): array
    {
        $sub = $this->current($company);
        $plan = $sub?->plan;

        return [
            'status' => $sub?->status->value ?? 'none',
            'plan' => $plan ? ['code' => $plan->code, 'name' => $plan->name, 'features' => $plan->features ?? []] : null,
            'trial_ends_at' => $sub?->trial_ends_at?->toIso8601String(),
            'ends_at' => $sub?->ends_at?->toIso8601String(),
            'usage' => collect(['vehicles', 'drivers', 'users', 'monthly_deliveries'])->mapWithKeys(fn ($r) => [$r => [
                'used' => $this->usage($company, $r),
                'limit' => $plan?->{'max_'.$r},
            ]]),
        ];
    }
}

<?php

namespace App\Services\Admin;

use App\Enums\CompanyStatus;
use App\Exceptions\DomainException;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/** Platform-level operations. Every access/action is audit-logged (company_id = target). */
class AdminService
{
    public function __construct(private readonly ActivityLogger $logger) {}

    public function stats(): array
    {
        $companies = Company::select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $active = CompanySubscription::with('plan')->whereIn('status', ['trialing', 'active'])->get()->filter->isCurrent();
        $mrr = $active->where('status.value', 'active')->reduce(fn ($c, $s) => $c + Money::toCents($s->plan->price_monthly), 0);

        $growth = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->startOfMonth()->subMonths($i);
            $growth[] = [
                'month' => $m->format('Y-m'),
                'companies' => Company::whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->count(),
                'deliveries' => Delivery::withoutGlobalScope('company')->whereBetween('created_at', [$m, $m->copy()->endOfMonth()])->count(),
            ];
        }

        return [
            'total_companies' => (int) $companies->sum(),
            'active_companies' => (int) ($companies[CompanyStatus::Active->value] ?? 0),
            'pending_companies' => (int) ($companies[CompanyStatus::Pending->value] ?? 0),
            'suspended_companies' => (int) ($companies[CompanyStatus::Suspended->value] ?? 0),
            'total_users' => User::count(),
            'total_vehicles' => Vehicle::withoutGlobalScope('company')->count(),
            'total_drivers' => Driver::withoutGlobalScope('company')->count(),
            'total_deliveries' => Delivery::withoutGlobalScope('company')->count(),
            'deliveries_last_30_days' => Delivery::withoutGlobalScope('company')->where('created_at', '>=', now()->subDays(30))->count(),
            'active_subscriptions' => $active->count(),
            'trialing_subscriptions' => $active->where('status.value', 'trialing')->count(),
            // MRR from recorded paid (status=active) subscriptions; no payment gateway is implemented.
            'mrr' => Money::fromCents($mrr),
            'growth' => $growth,
            'plans' => $active->groupBy(fn ($s) => $s->plan->name)->map->count(),
        ];
    }

    public function usage(Company $company): array
    {
        $q = fn ($m) => $m::withoutGlobalScope('company')->where('company_id', $company->id);

        return [
            'users' => $company->users()->count(),
            'vehicles' => $q(Vehicle::class)->count(),
            'drivers' => $q(Driver::class)->count(),
            'deliveries' => $q(Delivery::class)->count(),
            'deliveries_last_30_days' => $q(Delivery::class)->where('created_at', '>=', now()->subDays(30))->count(),
            'last_activity_at' => ActivityLog::where('company_id', $company->id)->max('created_at'),
        ];
    }

    public function setCompanyStatus(Company $company, CompanyStatus $status, User $admin, ?string $reason): Company
    {
        if ($status === CompanyStatus::Suspended && blank($reason)) {
            throw new DomainException('A suspension reason is required.', 'reason_required');
        }
        $company->forceFill([
            'status' => $status,
            'suspended_at' => $status === CompanyStatus::Suspended ? now() : null,
            'suspension_reason' => $status === CompanyStatus::Suspended ? $reason : null,
        ])->save();
        $this->logger->log('admin.company_'.$status->value, $company, ['reason' => $reason, 'admin_id' => $admin->id], $company->id);

        return $company;
    }

    public function setUserStatus(User $user, string $status, User $admin): User
    {
        if ($user->id === $admin->id) {
            throw new DomainException('You cannot suspend yourself.', 'self_change');
        }
        if ($user->is_super_admin && $status === 'suspended') {
            throw new DomainException('Platform administrators cannot be suspended here.', 'protected_user');
        }
        $user->forceFill(['status' => $status])->save();
        if ($status === 'suspended') {
            $user->tokens()->delete();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        }
        $this->logger->log('admin.user_'.$status, $user, ['admin_id' => $admin->id], null);

        return $user;
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\CompanyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\PlanRequest;
use App\Http\Resources\ActivityLogResource;
use App\Http\Resources\CompanyResource;
use App\Http\Resources\PlanResource;
use App\Models\ActivityLog;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\PlatformSetting;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Admin\AdminService;
use App\Services\Subscription\SubscriptionService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

class AdminController extends Controller
{
    public function __construct(private readonly AdminService $admin, private readonly ActivityLogger $logger) {}

    public function dashboard(): JsonResponse
    {
        return ApiResponse::ok($this->admin->stats());
    }

    public function companies(Request $request): JsonResponse
    {
        $page = Company::with(['owner:id,name,email', 'currentSubscription.plan'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', $this->like($s))->orWhere('contact_email', 'like', $this->like($s))))
            ->latest()->paginate(min(100, (int) $request->integer('per_page', 20)));

        return ApiResponse::ok(collect($page->items())->map(fn (Company $c) => $this->presentCompany($c, $request)), meta: ['pagination' => [
            'current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage(),
        ]]);
    }

    public function company(Request $request, Company $company): JsonResponse
    {
        // Viewing tenant usage is audited (spec: super admin must not bypass audit).
        $this->logger->log('admin.company_viewed', $company, ['admin_id' => $request->user()->id], $company->id);

        return ApiResponse::ok($this->presentCompany($company->load('owner', 'currentSubscription.plan'), $request) + [
            'usage' => $this->admin->usage($company),
            'subscriptions' => $company->subscriptions()->with('plan')->get()->map(fn ($s) => [
                'id' => $s->id, 'plan' => $s->plan->name, 'status' => $s->status->value, 'starts_at' => $s->starts_at?->toIso8601String(),
                'trial_ends_at' => $s->trial_ends_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(), 'notes' => $s->notes,
            ]),
            'recent_activity' => ActivityLogResource::collection(ActivityLog::where('company_id', $company->id)->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')->select('activity_logs.*', 'users.name as user_name')->latest('activity_logs.id')->limit(20)->get()),
        ]);
    }

    public function suspend(Request $request, Company $company): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);

        return ApiResponse::ok(new CompanyResource($this->admin->setCompanyStatus($company, CompanyStatus::Suspended, $request->user(), $data['reason'])), 'Company suspended.');
    }

    public function activate(Request $request, Company $company): JsonResponse
    {
        return ApiResponse::ok(new CompanyResource($this->admin->setCompanyStatus($company, CompanyStatus::Active, $request->user(), null)), 'Company activated.');
    }

    public function users(Request $request): JsonResponse
    {
        $page = User::withCount('companies')
            ->when($request->query('search'), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', $this->like($s))->orWhere('email', 'like', $this->like($s))))
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest()->paginate(min(100, (int) $request->integer('per_page', 25)));

        return ApiResponse::ok(collect($page->items())->map(fn (User $u) => [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'status' => $u->status->value, 'is_super_admin' => $u->is_super_admin,
            'email_verified' => $u->email_verified_at !== null, 'companies' => $u->companies_count,
            'last_login_at' => $u->last_login_at?->toIso8601String(), 'created_at' => $u->created_at->toIso8601String(),
        ]), meta: ['pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function userStatus(Request $request, User $user): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(['active', 'suspended'])]]);
        $this->admin->setUserStatus($user, $data['status'], $request->user());

        return ApiResponse::ok(null, 'User updated.');
    }

    public function plans(): JsonResponse
    {
        return ApiResponse::ok(PlanResource::collection(SubscriptionPlan::withCount(['subscriptions as active_subscriptions' => fn ($q) => $q->whereIn('status', ['trialing', 'active'])])->orderBy('sort_order')->get()));
    }

    public function storePlan(PlanRequest $request): JsonResponse
    {
        $plan = DB::transaction(function () use ($request) {
            if ($request->boolean('is_default')) {
                SubscriptionPlan::query()->update(['is_default' => false]);
            }

            return SubscriptionPlan::create($request->validated());
        });
        $this->logger->log('admin.plan_created', $plan, ['code' => $plan->code], null);

        return ApiResponse::created(new PlanResource($plan), 'Plan created.');
    }

    public function updatePlan(PlanRequest $request, SubscriptionPlan $plan): JsonResponse
    {
        DB::transaction(function () use ($request, $plan) {
            if ($request->boolean('is_default')) {
                SubscriptionPlan::whereKeyNot($plan->id)->update(['is_default' => false]);
            }
            $plan->update($request->validated());
        });
        $this->logger->log('admin.plan_updated', $plan, ['fields' => array_keys($plan->getChanges())], null);

        return ApiResponse::ok(new PlanResource($plan), 'Plan updated.');
    }

    public function subscriptions(Request $request): JsonResponse
    {
        // public_id must be selected: the list maps company_id from it (route binding key).
        $page = CompanySubscription::with(['company:id,public_id,name,status', 'plan:id,name,code,price_monthly,currency'])
            ->when($request->query('status'), fn ($q, $s) => $q->where('status', $s))
            ->latest('id')->paginate(30);

        return ApiResponse::ok(collect($page->items())->map(fn ($s) => [
            'id' => $s->id, 'company' => $s->company?->name, 'company_id' => $s->company?->public_id, 'plan' => $s->plan->name,
            'price_monthly' => $s->plan->price_monthly, 'currency' => $s->plan->currency, 'status' => $s->status->value, 'current' => $s->isCurrent(),
            'starts_at' => $s->starts_at?->toIso8601String(), 'trial_ends_at' => $s->trial_ends_at?->toIso8601String(), 'ends_at' => $s->ends_at?->toIso8601String(),
        ]), meta: ['pagination' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(), 'last_page' => $page->lastPage()]]);
    }

    public function changeSubscription(Request $request, Company $company, SubscriptionService $subs): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required', Rule::exists('subscription_plans', 'code')],
            'status' => ['required', Rule::in(['trialing', 'active'])],
            'ends_at' => ['nullable', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $plan = SubscriptionPlan::where('code', $data['plan'])->firstOrFail();
        $subs->changePlan($company, $plan, $request->user(), $data['status'], $data['ends_at'] ?? null, $data['notes'] ?? null);

        return ApiResponse::ok(null, 'Subscription updated.');
    }

    public function activity(Request $request): JsonResponse
    {
        $page = ActivityLog::query()
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->leftJoin('companies', 'companies.id', '=', 'activity_logs.company_id')
            ->select('activity_logs.*', 'users.name as user_name', 'companies.name as company_name')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->when($request->query('company'), fn ($q, $c) => $q->where('companies.public_id', $c))
            ->latest('activity_logs.id')->paginate(50);

        return ApiResponse::ok(ActivityLogResource::collection($page));
    }

    public function settings(): JsonResponse
    {
        return ApiResponse::ok(array_merge(self::DEFAULT_SETTINGS, PlatformSetting::pluck('value', 'key')->all()));
    }

    public const DEFAULT_SETTINGS = [
        'registration_enabled' => true,
        'require_company_approval' => false,
        'support_email' => null,
        'maintenance_banner' => null,
    ];

    public function updateSettings(Request $request): JsonResponse
    {
        $data = $request->validate([
            'registration_enabled' => ['sometimes', 'boolean'],
            'require_company_approval' => ['sometimes', 'boolean'],
            'support_email' => ['nullable', 'email'],
            'maintenance_banner' => ['nullable', 'string', 'max:255'],
        ]);
        foreach ($data as $k => $v) {
            PlatformSetting::updateOrCreate(['key' => $k], ['value' => $v]);
        }
        cache()->forget('platform_settings');
        $this->logger->log('admin.settings_updated', null, ['keys' => array_keys($data)], null);

        return $this->settings();
    }

    /** System health: DB, queue backlog, failed jobs, scheduler heartbeat, storage, broadcasting. */
    public function health(): JsonResponse
    {
        $check = function (callable $fn) {
            try {
                return ['ok' => true] + $fn();
            } catch (Throwable $e) {
                return ['ok' => false, 'error' => class_basename($e)];
            }
        };

        return ApiResponse::ok([
            'database' => $check(fn () => ['driver' => DB::getDriverName(), 'latency_ms' => (function () {
                $t = microtime(true);
                DB::select('select 1');

                return round((microtime(true) - $t) * 1000, 1);
            })()]),
            'queue' => $check(fn () => ['connection' => config('queue.default'), 'pending_jobs' => DB::table('jobs')->count(), 'failed_jobs' => DB::table('failed_jobs')->count()]),
            'scheduler' => $check(fn () => ['last_run_at' => cache('scheduler:heartbeat'), 'ok_recent' => cache('scheduler:heartbeat') && now()->diffInMinutes(cache('scheduler:heartbeat'), true) < 10]),
            'storage' => $check(fn () => ['disk_free_gb' => round(disk_free_space(storage_path()) / 1073741824, 1)]),
            'broadcasting' => ['ok' => true, 'driver' => config('broadcasting.default')],
            'maps' => ['ok' => (bool) config('fleetflow.maps.provider'), 'provider' => config('fleetflow.maps.provider')],
            'webhooks' => $check(fn () => ['pending' => DB::table('webhook_deliveries')->where('status', 'pending')->count(), 'failed' => DB::table('webhook_deliveries')->where('status', 'failed')->count()]),
            'php' => PHP_VERSION,
            'laravel' => app()->version(),
        ]);
    }

    private function presentCompany(Company $c, Request $request): array
    {
        $sub = $c->currentSubscription;

        return (new CompanyResource($c))->resolve($request) + [
            'owner' => $c->owner ? ['name' => $c->owner->name, 'email' => $c->owner->email] : null,
            'suspension_reason' => $c->suspension_reason,
            'subscription' => $sub ? ['plan' => $sub->plan?->name, 'status' => $sub->status->value, 'current' => $sub->isCurrent(), 'trial_ends_at' => $sub->trial_ends_at?->toIso8601String()] : null,
        ];
    }
}

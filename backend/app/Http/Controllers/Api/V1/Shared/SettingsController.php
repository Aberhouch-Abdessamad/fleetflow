<?php

namespace App\Http\Controllers\Api\V1\Shared;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\SubscriptionPlan;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenant\CompanySettings;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function __construct(private readonly CompanySettings $settings, private readonly TenantContext $tenant) {}

    public function show(): JsonResponse
    {
        return ApiResponse::ok($this->settings->all($this->tenant->companyOrFail()));
    }

    public function update(Request $request, ActivityLogger $logger): JsonResponse
    {
        abort_unless($request->user()->can('company.settings'), 403);
        $data = $request->validate(CompanySettings::RULES);
        $company = $this->tenant->companyOrFail();
        $before = $this->settings->all($company);
        $after = $this->settings->update($company, $data);
        $logger->log('company.settings_updated', $company, ['changed' => array_keys(array_diff_assoc(array_map('json_encode', $after), array_map('json_encode', $before)))]);

        return ApiResponse::ok($after, 'Settings saved.');
    }

    public function subscription(SubscriptionService $subs): JsonResponse
    {
        return ApiResponse::ok($subs->summary($this->tenant->companyOrFail()) + [
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(['code', 'name', 'price_monthly', 'currency', 'max_vehicles', 'max_drivers', 'max_users', 'max_monthly_deliveries', 'features']),
        ]);
    }

    public function activity(Request $request): JsonResponse
    {
        abort_unless($request->user()->can('company.settings'), 403);
        $page = ActivityLog::query()->where('company_id', $this->tenant->id())
            ->leftJoin('users', 'users.id', '=', 'activity_logs.user_id')
            ->select('activity_logs.*', 'users.name as user_name')
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', $a.'%'))
            ->latest('activity_logs.id')->paginate(30);

        return ApiResponse::ok(ActivityLogResource::collection($page));
    }
}

<?php

namespace App\Http\Middleware;

use App\Exceptions\DomainException;
use App\Models\ApiClient;
use App\Models\Company;
use App\Services\Subscription\SubscriptionService;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Integration endpoints: the tenant is the API client's own company — never from input. */
class ResolveIntegrationClient
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $client = $request->user();
        if (! $client instanceof ApiClient || ! $client->is_active) {
            throw new AuthenticationException;
        }
        $company = Company::find($client->company_id);
        if (! $company || ! $company->status->isOperational()) {
            throw new DomainException('This company workspace is not active.', 'tenant_suspended', 423);
        }
        $this->tenant->set($company);
        if (! app(SubscriptionService::class)->hasFeature($company, 'api_integrations')) {
            throw new DomainException('API integrations are not included in this subscription.', 'plan_feature_unavailable', 402);
        }

        return $next($request);
    }
}

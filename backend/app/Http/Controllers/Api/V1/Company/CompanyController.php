<?php

namespace App\Http\Controllers\Api\V1\Company;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Company\CompanyRequest;
use App\Http\Resources\CompanyResource;
use App\Services\Tenant\CompanyOnboardingService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function __construct(
        private readonly CompanyOnboardingService $onboarding,
        private readonly TenantContext $tenant,
    ) {}

    public function store(CompanyRequest $request): JsonResponse
    {
        $company = $this->onboarding->create($request->user(), $request->companyData());

        return ApiResponse::created(new CompanyResource($company), 'Company workspace created.');
    }

    public function current(): JsonResponse
    {
        return ApiResponse::ok(new CompanyResource($this->tenant->companyOrFail()));
    }

    public function update(CompanyRequest $request): JsonResponse
    {
        if (! $request->user()->can('company.update')) {
            throw new AuthorizationException;
        }

        $company = $this->onboarding->update($this->tenant->companyOrFail(), $request->companyData());

        return ApiResponse::ok(new CompanyResource($company), 'Company updated.');
    }

    /** Switch the default workspace — only to a company the user already belongs to. */
    public function switch(Request $request, string $publicId): JsonResponse
    {
        $company = $request->user()->activeCompanies()->where('companies.public_id', $publicId)->firstOrFail();
        $request->user()->forceFill(['current_company_id' => $company->id])->save();

        return ApiResponse::ok(new CompanyResource($company), 'Workspace switched.');
    }
}

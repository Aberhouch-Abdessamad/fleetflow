<?php

namespace App\Services\Tenant;

use App\Enums\CompanyRole;
use App\Enums\CompanyStatus;
use App\Enums\MembershipStatus;
use App\Models\Company;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Support\Platform;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CompanyOnboardingService
{
    public function __construct(
        private readonly ActivityLogger $logger,
        private readonly TenantContext $tenant,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** Creates the workspace and makes the creator its Company Admin — atomically. */
    public function create(User $owner, array $data): Company
    {
        return DB::transaction(function () use ($owner, $data) {
            $company = new Company($data);
            $company->slug = $this->uniqueSlug($data['name']);
            $company->status = Platform::setting('require_company_approval')
                ? CompanyStatus::Pending
                : CompanyStatus::Active;
            $company->owner()->associate($owner);
            $company->save();

            $company->users()->attach($owner->id, [
                'status' => MembershipStatus::Active->value,
                'joined_at' => now(),
            ]);

            $this->tenant->runAs($company, function () use ($owner) {
                $owner->unsetRelation('roles')->assignRole(CompanyRole::CompanyAdmin->value);
            });

            $owner->forceFill(['current_company_id' => $company->id])->save();
            $this->subscriptions->startTrial($company);

            $this->logger->log('company.created', $company, ['name' => $company->name], $company->id);

            return $company;
        });
    }

    public function update(Company $company, array $data): Company
    {
        $company->fill($data)->save();
        $this->logger->log('company.updated', $company, ['fields' => array_keys($company->getChanges())], $company->id);

        return $company->refresh();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'company';
        $slug = $base;
        $i = 2;
        while (Company::withTrashed()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}

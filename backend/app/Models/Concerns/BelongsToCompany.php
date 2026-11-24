<?php

namespace App\Models\Concerns;

use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Tenant isolation for every company-owned model.
 *
 *  - Global scope: queries only see rows of the current tenant. When no tenant
 *    is resolved the scope matches NOTHING (fail closed), so a missing
 *    middleware can never leak data. Platform code that legitimately needs all
 *    tenants must call withoutGlobalScope('company') explicitly.
 *  - Creating: company_id is always forced to the current tenant, overwriting
 *    anything that came from user input.
 */
trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope('company', function (Builder $builder) {
            $tenantId = app(TenantContext::class)->id();
            $column = $builder->getModel()->qualifyColumn('company_id');

            $tenantId === null
                ? $builder->whereRaw('1 = 0')
                : $builder->where($column, $tenantId);
        });

        static::creating(function ($model) {
            $tenantId = app(TenantContext::class)->id();
            if ($tenantId !== null) {
                $model->company_id = $tenantId;
            } elseif (empty($model->company_id)) {
                throw new \LogicException(static::class.' cannot be created without a tenant context.');
            }
        });

        // A row can never be moved to another tenant.
        static::updating(function ($model) {
            if ($model->isDirty('company_id')) {
                $model->company_id = $model->getOriginal('company_id');
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}

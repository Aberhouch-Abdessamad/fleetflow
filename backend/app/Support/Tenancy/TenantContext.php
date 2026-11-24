<?php

namespace App\Support\Tenancy;

use App\Exceptions\DomainException;
use App\Models\Company;

/**
 * Holds the company resolved for the current request. Populated ONLY by
 * ResolveTenant middleware (from the authenticated user's memberships) or by
 * trusted server-side code such as queued jobs — never from request input.
 */
final class TenantContext
{
    private ?Company $company = null;

    public function set(?Company $company): void
    {
        $this->company = $company;
        // Scope Spatie role/permission checks to this company.
        setPermissionsTeamId($company?->id);
    }

    public function company(): ?Company
    {
        return $this->company;
    }

    public function id(): ?int
    {
        return $this->company?->id;
    }

    public function has(): bool
    {
        return $this->company !== null;
    }

    public function companyOrFail(): Company
    {
        return $this->company ?? throw new DomainException('No active company workspace.', 'tenant_missing', 403);
    }

    /** Run a callback inside another tenant context (jobs, console commands, tests). */
    public function runAs(Company $company, callable $callback): mixed
    {
        $previous = $this->company;
        $this->set($company);
        try {
            return $callback();
        } finally {
            $this->set($previous);
        }
    }
}

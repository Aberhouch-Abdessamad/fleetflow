<?php

namespace App\Http\Middleware;

use App\Enums\MembershipStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Support\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the company workspace for the authenticated user.
 *
 * The optional X-Company header (company public_id) only SELECTS among the
 * companies the user is an active member of; it can never grant access.
 * Falls back to the user's current_company_id, then their first membership.
 * Any company_id in the request body/query is ignored by design.
 */
class ResolveTenant
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function handle(Request $request, Closure $next, string $mode = 'required'): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        $memberships = $user->companies()
            ->wherePivot('status', MembershipStatus::Active->value);

        $requested = $request->header('X-Company');

        /** @var Company|null $company */
        $company = match (true) {
            filled($requested) => (clone $memberships)->where('companies.public_id', $requested)->first()
                ?? throw new DomainException('You are not a member of this company.', 'tenant_forbidden', 403),
            $user->current_company_id !== null => (clone $memberships)->where('companies.id', $user->current_company_id)->first()
                ?? (clone $memberships)->orderBy('companies.id')->first(),
            default => (clone $memberships)->orderBy('companies.id')->first(),
        };

        if ($company === null) {
            if ($mode === 'optional') {
                return $next($request);
            }
            throw new DomainException('Create or join a company to continue.', 'tenant_missing', 403);
        }

        if (! $company->status->isOperational() && $mode !== 'optional') {
            throw new DomainException('This company workspace is '.$company->status->value.'.', 'tenant_'.$company->status->value, 423);
        }

        $this->tenant->set($company);
        // Drop any role/permission relations loaded under a different team.
        $user->unsetRelation('roles')->unsetRelation('permissions');

        return $next($request);
    }
}

<?php

use App\Models\Company;
use App\Models\Driver;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\Broadcast;

/*
| Private, tenant-authorised channels.
|  company.{publicId} — fleet-wide events; members with tracking or dispatch access.
|  driver.{driverId}  — a single driver's assignment updates; only that driver.
*/

Broadcast::channel('company.{publicId}', function (User $user, string $publicId) {
    $company = $user->activeCompanies()->where('companies.public_id', $publicId)->first();
    if (! $company || ! $company->status->isOperational()) {
        return false;
    }

    return app(TenantContext::class)->runAs($company, fn () => $user->can('tracking.view') || $user->can('dispatch.board') || $user->can('deliveries.view'));
});

Broadcast::channel('driver.{driverId}', function (User $user, int $driverId) {
    $driver = Driver::withoutGlobalScope('company')->find($driverId);

    return $driver && $driver->user_id === $user->id && $user->isMemberOf(Company::find($driver->company_id));
});

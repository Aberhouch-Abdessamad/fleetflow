<?php

namespace App\Services;

use App\Models\Company;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Resolves notification recipients by company-scoped permission (Spatie teams). */
final class Notify
{
    /** @return Collection<int, User> */
    public static function usersWithPermission(Company|int $company, string $permission): Collection
    {
        $companyId = $company instanceof Company ? $company->id : $company;

        $roleIds = DB::table('role_has_permissions as rhp')
            ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
            ->where('p.name', $permission)->pluck('rhp.role_id');

        $userIds = DB::table('model_has_roles')
            ->where('company_id', $companyId)->where('model_type', 'user')->whereIn('role_id', $roleIds)
            ->pluck('model_id')
            ->merge(DB::table('model_has_permissions as mhp')->join('permissions as p', 'p.id', '=', 'mhp.permission_id')
                ->where('mhp.company_id', $companyId)->where('mhp.model_type', 'user')->where('p.name', $permission)->pluck('mhp.model_id'))
            ->unique();

        return User::whereIn('id', $userIds)
            ->whereHas('companies', fn ($q) => $q->where('companies.id', $companyId)->where('company_users.status', 'active'))
            ->where('status', 'active')->get();
    }

    public static function companyUsersWith(Company|int $company, string $permission, Notification $notification, ?int $exceptUserId = null): void
    {
        self::usersWithPermission($company, $permission)
            ->reject(fn (User $u) => $u->id === $exceptUserId)
            ->each(fn (User $u) => $u->notify($notification));
    }
}

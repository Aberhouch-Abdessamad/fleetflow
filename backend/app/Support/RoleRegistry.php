<?php

namespace App\Support;

use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Syncs config('fleetflow.roles') into Spatie. Role definitions are global
 * (company_id NULL) and shared by every tenant; ASSIGNMENTS are per company.
 */
final class RoleRegistry
{
    public static function sync(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $previousTeam = getPermissionsTeamId();
        setPermissionsTeamId(null);

        foreach (config('fleetflow.permissions') as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (config('fleetflow.roles') as $role => $permissions) {
            $model = Role::query()->whereNull('company_id')
                ->where(['name' => $role, 'guard_name' => 'web'])->first()
                ?? Role::create(['name' => $role, 'guard_name' => 'web', 'company_id' => null]);
            $model->syncPermissions($permissions);
        }

        setPermissionsTeamId($previousTeam);
        $registrar->forgetCachedPermissions();
    }
}

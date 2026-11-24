<?php

use App\Support\RoleRegistry;
use Illuminate\Support\Facades\Artisan;

Artisan::command('fleetflow:sync-roles', function () {
    RoleRegistry::sync();
    $this->info('Roles and permissions synced from config/fleetflow.php.');
})->purpose('Sync the FleetFlow role → permission matrix into the database');

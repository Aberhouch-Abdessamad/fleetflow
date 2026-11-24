<?php

/*
| FleetFlow domain configuration.
| The permission matrix is the single source of truth for company roles; run
| `php artisan fleetflow:sync-roles` (also done by the seeder) after editing.
*/

$all = [
    'company.view', 'company.update', 'company.settings',
    'users.view', 'users.invite', 'users.manage', 'roles.manage',
    'vehicles.view', 'vehicles.manage', 'vehicles.assign',
    'drivers.view', 'drivers.view_private', 'drivers.manage',
    'customers.view', 'customers.manage',
    'deliveries.view', 'deliveries.create', 'deliveries.update', 'deliveries.cancel', 'deliveries.assign',
    'deliveries.view_own', 'deliveries.update_own',
    'dispatch.board', 'routes.view', 'routes.manage',
    'tracking.view', 'tracking.report_location',
    'maintenance.view', 'maintenance.manage',
    'fuel.view', 'fuel.manage', 'fuel.create_own',
    'expenses.view', 'expenses.manage', 'expenses.approve', 'expenses.create_own',
    'invoices.view', 'invoices.manage', 'payments.manage',
    'reports.fleet', 'reports.deliveries', 'reports.drivers', 'reports.financial',
    'integrations.manage',
];

return [
    'permissions' => $all,

    'roles' => [
        'company_admin' => $all,

        'fleet_manager' => [
            'company.view', 'vehicles.view', 'vehicles.manage', 'vehicles.assign',
            'drivers.view', 'drivers.view_private', 'drivers.manage',
            'deliveries.view', 'tracking.view', 'routes.view',
            'maintenance.view', 'maintenance.manage', 'fuel.view', 'fuel.manage',
            'expenses.view', 'reports.fleet', 'reports.drivers',
        ],

        'dispatcher' => [
            'company.view', 'vehicles.view', 'drivers.view',
            'customers.view', 'customers.manage',
            'deliveries.view', 'deliveries.create', 'deliveries.update', 'deliveries.cancel', 'deliveries.assign',
            'dispatch.board', 'routes.view', 'routes.manage', 'tracking.view', 'reports.deliveries',
        ],

        'driver' => [
            'company.view', 'deliveries.view_own', 'deliveries.update_own',
            'tracking.report_location', 'fuel.create_own', 'expenses.create_own',
        ],

        'accountant' => [
            'company.view', 'vehicles.view', 'drivers.view', 'customers.view', 'deliveries.view',
            'fuel.view', 'fuel.manage', 'maintenance.view',
            'expenses.view', 'expenses.manage', 'expenses.approve',
            'invoices.view', 'invoices.manage', 'payments.manage', 'reports.financial',
        ],

        'customer' => ['deliveries.view_own'],
    ],

    'tracking' => [
        'min_interval_seconds' => (int) env('TRACKING_MIN_INTERVAL_SECONDS', 5),
        'stale_after_seconds' => (int) env('TRACKING_STALE_AFTER_SECONDS', 120),
        'retention_days' => (int) env('TRACKING_RETENTION_DAYS', 90),
    ],

    'maps' => [
        // mapbox | google | osm (default — key-less OSM/Nominatim + OSRM); none disables everything
        'provider' => env('MAPS_PROVIDER', 'osm'),
        'mapbox_token' => env('MAPBOX_ACCESS_TOKEN'),
        'google_key' => env('GOOGLE_MAPS_API_KEY'),
        'osrm_url' => env('OSRM_URL', 'https://router.project-osrm.org'),
    ],
];

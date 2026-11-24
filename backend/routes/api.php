<?php

use App\Http\Controllers\Api\V1\Admin\AdminController;
use App\Http\Controllers\Api\V1\Auth\AccountController;
use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\Delivery\CustomerController;
use App\Http\Controllers\Api\V1\Delivery\DeliveryController;
use App\Http\Controllers\Api\V1\Dispatch\DispatchController;
use App\Http\Controllers\Api\V1\Driver\DriverAppController;
use App\Http\Controllers\Api\V1\Driver\DriverController;
use App\Http\Controllers\Api\V1\Finance\ExpenseController;
use App\Http\Controllers\Api\V1\Finance\FuelController;
use App\Http\Controllers\Api\V1\Finance\InvoiceController;
use App\Http\Controllers\Api\V1\Finance\MaintenanceController;
use App\Http\Controllers\Api\V1\Fleet\VehicleController;
use App\Http\Controllers\Api\V1\Integration\ExternalApiController;
use App\Http\Controllers\Api\V1\Integration\IntegrationSettingsController;
use App\Http\Controllers\Api\V1\Reports\ReportController;
use App\Http\Controllers\Api\V1\Route\RouteController;
use App\Http\Controllers\Api\V1\Shared\FileController;
use App\Http\Controllers\Api\V1\Shared\MapsController;
use App\Http\Controllers\Api\V1\Shared\NotificationController;
use App\Http\Controllers\Api\V1\Shared\SettingsController;
use App\Http\Controllers\Api\V1\System\HealthController;
use App\Http\Controllers\Api\V1\Team\InvitationController;
use App\Http\Controllers\Api\V1\Team\TeamController;
use App\Http\Controllers\Api\V1\Tracking\PublicTrackingController;
use App\Http\Controllers\Api\V1\Tracking\TrackingController;
use Illuminate\Support\Facades\Route;

/*
| FleetFlow API v1
| Layers: auth:sanctum → active → tenant (company resolved server-side) → permission:* → policies.
| Every model bound from the URL is resolved through the tenant scope, so another
| company's id is always a 404.
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // ---------------------------------------------------------------- public
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
        Route::post('forgot-password', [AccountController::class, 'forgot'])->name('auth.forgot');
        Route::post('reset-password', [AccountController::class, 'reset'])->name('auth.reset');
    });
    Route::get('auth/verify/{id}/{hash}', [AccountController::class, 'verify'])->middleware('throttle:auth')->name('auth.verify');

    Route::middleware('throttle:public-tracking')->group(function () {
        Route::get('tracking/public/{token}', [PublicTrackingController::class, 'show'])->name('tracking.public');
        Route::post('tracking/public/{token}/rating', [PublicTrackingController::class, 'rate'])->name('tracking.public.rate');
    });

    Route::middleware('throttle:auth')->group(function () {
        Route::get('invitations/{token}', [InvitationController::class, 'show'])->name('invitations.show');
        Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
    });

    // ---------------------------------------------------------------- integrations (machine clients)
    Route::prefix('integrations')->middleware(['auth:sanctum', 'integration', 'throttle:integrations'])->name('integrations.')->group(function () {
        Route::post('deliveries', [ExternalApiController::class, 'createDelivery'])->name('deliveries.store');
        Route::get('deliveries/{externalReference}', [ExternalApiController::class, 'showDelivery'])->name('deliveries.show');
        Route::post('deliveries/{externalReference}/cancel', [ExternalApiController::class, 'cancelDelivery'])->name('deliveries.cancel');
        Route::post('shipments', [ExternalApiController::class, 'createShipment'])->name('shipments.store');
    });

    // ---------------------------------------------------------------- authenticated users
    Route::middleware(['auth:sanctum', 'user', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('auth/me', [AuthController::class, 'me'])->middleware('tenant:optional')->name('auth.me');
        Route::post('auth/email/resend', [AccountController::class, 'resendVerification'])->middleware('throttle:6,1')->name('auth.resend');
        Route::put('auth/profile', [AccountController::class, 'updateProfile'])->middleware('tenant:optional')->name('auth.profile');
        Route::put('auth/password', [AccountController::class, 'updatePassword'])->middleware('throttle:sensitive')->name('auth.password');

        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{publicId}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

        // ------------------------------------------------------------ super admin
        Route::prefix('admin')->middleware('super_admin')->name('admin.')->group(function () {
            Route::get('dashboard', [AdminController::class, 'dashboard']);
            Route::get('health', [AdminController::class, 'health']);
            Route::get('companies', [AdminController::class, 'companies']);
            Route::get('companies/{company}', [AdminController::class, 'company']);
            Route::patch('companies/{company}/suspend', [AdminController::class, 'suspend']);
            Route::patch('companies/{company}/activate', [AdminController::class, 'activate']);
            Route::put('companies/{company}/subscription', [AdminController::class, 'changeSubscription']);
            Route::get('users', [AdminController::class, 'users']);
            Route::patch('users/{user}/status', [AdminController::class, 'userStatus']);
            Route::get('plans', [AdminController::class, 'plans']);
            Route::post('plans', [AdminController::class, 'storePlan']);
            Route::put('plans/{plan}', [AdminController::class, 'updatePlan']);
            Route::get('subscriptions', [AdminController::class, 'subscriptions']);
            Route::get('activity-logs', [AdminController::class, 'activity']);
            Route::get('settings', [AdminController::class, 'settings']);
            Route::put('settings', [AdminController::class, 'updateSettings']);
        });

        // ------------------------------------------------------------ tenant workspace
        Route::middleware('tenant')->group(function () {
            Route::get('companies/current', [CompanyController::class, 'current'])->name('companies.current');
            Route::put('companies/current', [CompanyController::class, 'update'])->name('companies.update');
            Route::get('companies/current/settings', [SettingsController::class, 'show']);
            Route::put('companies/current/settings', [SettingsController::class, 'update']);
            Route::get('companies/current/subscription', [SettingsController::class, 'subscription']);
            Route::get('companies/current/activity', [SettingsController::class, 'activity']);

            Route::get('dashboard', [ReportController::class, 'dashboard']);
            Route::get('files/{kind}/{id}', FileController::class)->whereNumber('id');
            Route::get('maps/config', [MapsController::class, 'config']);
            Route::get('maps/geocode', [MapsController::class, 'geocode'])->middleware('throttle:30,1');
            Route::get('maps/reverse', [MapsController::class, 'reverse'])->middleware('throttle:30,1');

            Route::get('notifications', [NotificationController::class, 'index']);
            Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
            Route::post('notifications/read-all', [NotificationController::class, 'markAllRead']);
            Route::post('notifications/{id}/read', [NotificationController::class, 'markRead']);

            // Team
            Route::middleware('permission:users.view')->group(function () {
                Route::get('team', [TeamController::class, 'index']);
            });
            Route::middleware('permission:users.invite')->group(function () {
                Route::post('team/invitations', [TeamController::class, 'invite']);
                Route::delete('team/invitations/{invitation}', [TeamController::class, 'revoke']);
            });
            Route::patch('team/members/{user}', [TeamController::class, 'updateMember'])->middleware('permission:users.manage');

            // Vehicles
            Route::get('vehicle-types', [VehicleController::class, 'types']);
            Route::post('vehicle-types', [VehicleController::class, 'storeType'])->middleware('permission:vehicles.manage');
            Route::middleware('permission:vehicles.view')->group(function () {
                Route::get('vehicles', [VehicleController::class, 'index']);
                Route::get('vehicles/{vehicle}', [VehicleController::class, 'show']);
                Route::get('vehicles/{vehicle}/history', [VehicleController::class, 'history']);
                Route::get('vehicles/{vehicle}/documents', [VehicleController::class, 'documents']);
            });
            Route::middleware('permission:vehicles.manage')->group(function () {
                Route::post('vehicles', [VehicleController::class, 'store']);
                Route::match(['put', 'post'], 'vehicles/{vehicle}', [VehicleController::class, 'update']); // POST for multipart
                Route::delete('vehicles/{vehicle}', [VehicleController::class, 'destroy']);
                Route::post('vehicles/{id}/restore', [VehicleController::class, 'restore'])->whereNumber('id');
                Route::patch('vehicles/{vehicle}/status', [VehicleController::class, 'setStatus']);
                Route::post('vehicles/{vehicle}/documents', [VehicleController::class, 'storeDocument']);
                Route::delete('vehicles/{vehicle}/documents/{document}', [VehicleController::class, 'destroyDocument']);
            });
            Route::middleware('permission:vehicles.assign')->group(function () {
                Route::post('vehicles/{vehicle}/assign', [VehicleController::class, 'assignDriver']);
                Route::post('vehicles/{vehicle}/unassign', [VehicleController::class, 'unassignDriver']);
            });

            // Drivers
            Route::middleware('permission:drivers.view')->group(function () {
                Route::get('drivers', [DriverController::class, 'index']);
                Route::get('drivers/{driver}', [DriverController::class, 'show']);
                Route::get('drivers/{driver}/deliveries', [DriverController::class, 'deliveries']);
                Route::get('drivers/{driver}/performance', [DriverController::class, 'performance']);
                Route::get('drivers/{driver}/availability', [DriverController::class, 'availabilityHistory']);
                Route::get('drivers/{driver}/shifts', [DriverController::class, 'shifts']);
                Route::get('drivers/{driver}/assignments', [DriverController::class, 'assignments']);
                Route::get('drivers/{driver}/documents', [DriverController::class, 'documents']);
            });
            Route::middleware('permission:drivers.manage')->group(function () {
                Route::post('drivers', [DriverController::class, 'store']);
                Route::match(['put', 'post'], 'drivers/{driver}', [DriverController::class, 'update']);
                Route::delete('drivers/{driver}', [DriverController::class, 'destroy']);
                Route::patch('drivers/{driver}/status', [DriverController::class, 'setStatus']);
                Route::put('drivers/{driver}/availability', [DriverController::class, 'availability']);
                Route::post('drivers/{driver}/documents', [DriverController::class, 'storeDocument']);
            });

            // Customers
            Route::middleware('permission:customers.view')->group(function () {
                Route::get('customers', [CustomerController::class, 'index']);
                Route::get('customers/{customer}', [CustomerController::class, 'show']);
                Route::get('customers/{customer}/deliveries', [CustomerController::class, 'deliveries']);
            });
            Route::middleware('permission:customers.manage')->group(function () {
                Route::post('customers', [CustomerController::class, 'store']);
                Route::put('customers/{customer}', [CustomerController::class, 'update']);
                Route::delete('customers/{customer}', [CustomerController::class, 'destroy']);
                Route::post('customers/{customer}/addresses', [CustomerController::class, 'storeAddress']);
                Route::put('customers/{customer}/addresses/{address}', [CustomerController::class, 'updateAddress']);
                Route::delete('customers/{customer}/addresses/{address}', [CustomerController::class, 'destroyAddress']);
            });

            // Deliveries (view rules are enforced inside — drivers see their own)
            Route::get('deliveries', [DeliveryController::class, 'index']);
            Route::get('deliveries/export', [DeliveryController::class, 'export']);
            Route::get('deliveries/{delivery}', [DeliveryController::class, 'show']);
            Route::get('deliveries/{delivery}/history', [DeliveryController::class, 'history']);
            Route::patch('deliveries/{delivery}/status', [DeliveryController::class, 'status']);
            Route::post('deliveries/{delivery}/proof', [DeliveryController::class, 'proof'])->middleware('throttle:30,1');
            Route::post('deliveries', [DeliveryController::class, 'store'])->middleware('permission:deliveries.create');
            Route::put('deliveries/{delivery}', [DeliveryController::class, 'update'])->middleware('permission:deliveries.update');
            Route::delete('deliveries/{delivery}', [DeliveryController::class, 'destroy'])->middleware('permission:deliveries.cancel');
            Route::post('deliveries/{delivery}/cancel', [DeliveryController::class, 'cancel']);
            Route::post('deliveries/{delivery}/otp', [DeliveryController::class, 'regenerateOtp']);
            Route::middleware('permission:deliveries.assign')->group(function () {
                Route::post('deliveries/{delivery}/assign', [DeliveryController::class, 'assign']);
                Route::post('deliveries/{delivery}/reassign', [DeliveryController::class, 'assign']);
            });

            // Dispatch
            Route::middleware('permission:dispatch.board')->group(function () {
                Route::get('dispatch/board', [DispatchController::class, 'board']);
                Route::get('dispatch/available-drivers', [DispatchController::class, 'availableDrivers']);
                Route::get('dispatch/available-vehicles', [DispatchController::class, 'availableVehicles']);
                Route::get('dispatch/assignments', [DispatchController::class, 'index']);
            });
            Route::middleware('permission:deliveries.assign')->group(function () {
                Route::post('dispatch/check', [DispatchController::class, 'check']);
                Route::post('dispatch/assignments', [DispatchController::class, 'store']);
                Route::delete('dispatch/assignments/{assignment}', [DispatchController::class, 'destroy']);
            });

            // Routes
            Route::middleware('permission:routes.view')->group(function () {
                Route::get('routes', [RouteController::class, 'index']);
                Route::get('routes/{route}', [RouteController::class, 'show']);
            });
            Route::middleware('permission:routes.manage')->group(function () {
                Route::post('routes', [RouteController::class, 'store']);
                Route::put('routes/{route}', [RouteController::class, 'update']);
                Route::delete('routes/{route}', [RouteController::class, 'destroy']);
                Route::post('routes/{route}/stops', [RouteController::class, 'addStops']);
                Route::delete('routes/{route}/stops/{stop}', [RouteController::class, 'removeStop']);
                Route::post('routes/{route}/optimize', [RouteController::class, 'optimize'])->middleware('throttle:10,1');
                Route::post('routes/{route}/start', [RouteController::class, 'start']);
                Route::post('routes/{route}/complete', [RouteController::class, 'complete']);
            });

            // Tracking
            Route::post('tracking/location', [TrackingController::class, 'store'])->middleware(['permission:tracking.report_location', 'throttle:tracking']);
            Route::middleware('permission:tracking.view')->group(function () {
                Route::get('tracking/vehicles', [TrackingController::class, 'vehicles']);
                Route::get('tracking/vehicles/{vehicle}', [TrackingController::class, 'vehicle']);
                Route::get('tracking/vehicles/{vehicle}/history', [TrackingController::class, 'history']);
            });
            Route::get('tracking/deliveries/{delivery}', [TrackingController::class, 'delivery']);

            // Driver app
            Route::prefix('driver')->middleware('permission:deliveries.view_own')->group(function () {
                Route::get('dashboard', [DriverAppController::class, 'dashboard']);
                Route::get('history', [DriverAppController::class, 'history']);
                Route::get('deliveries/{delivery}', [DriverAppController::class, 'delivery']);
                Route::post('deliveries/{delivery}/issue', [DriverAppController::class, 'reportIssue']);
                Route::post('assignments/{assignment}/respond', [DriverAppController::class, 'respond']);
                Route::post('shift/start', [DriverAppController::class, 'startShift']);
                Route::post('shift/end', [DriverAppController::class, 'endShift']);
            });

            // Maintenance
            Route::middleware('permission:maintenance.view')->group(function () {
                Route::get('maintenance', [MaintenanceController::class, 'index']);
                Route::get('maintenance/reminders', [MaintenanceController::class, 'reminders']);
                Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show']);
            });
            Route::middleware('permission:maintenance.manage')->group(function () {
                Route::post('maintenance', [MaintenanceController::class, 'store']);
                Route::match(['put', 'post'], 'maintenance/{maintenance}', [MaintenanceController::class, 'update']);
                Route::delete('maintenance/{maintenance}', [MaintenanceController::class, 'destroy']);
                Route::post('maintenance/reminders/{reminder}/dismiss', [MaintenanceController::class, 'dismissReminder']);
            });

            // Fuel & expenses (driver self-service rules enforced in controllers/policies)
            Route::get('fuel-records', [FuelController::class, 'index']);
            Route::post('fuel-records', [FuelController::class, 'store']);
            Route::get('expenses', [ExpenseController::class, 'index']);
            Route::get('expenses/export', [ExpenseController::class, 'export'])->middleware('permission:expenses.view');
            Route::post('expenses', [ExpenseController::class, 'store']);
            Route::get('expenses/{expense}', [ExpenseController::class, 'show']);
            Route::match(['put', 'post'], 'expenses/{expense}', [ExpenseController::class, 'update']);
            Route::delete('expenses/{expense}', [ExpenseController::class, 'destroy']);
            Route::patch('expenses/{expense}/approve', [ExpenseController::class, 'approve']);
            Route::patch('expenses/{expense}/reject', [ExpenseController::class, 'reject']);

            // Financial
            Route::middleware('permission:invoices.view')->group(function () {
                Route::get('invoices', [InvoiceController::class, 'index']);
                Route::get('invoices/billable', [InvoiceController::class, 'billable']);
                Route::get('invoices/{invoice}', [InvoiceController::class, 'show']);
            });
            Route::middleware('permission:invoices.manage')->group(function () {
                Route::post('invoices', [InvoiceController::class, 'store']);
                Route::post('invoices/{invoice}/issue', [InvoiceController::class, 'issue']);
                Route::post('invoices/{invoice}/void', [InvoiceController::class, 'void']);
            });
            Route::post('invoices/{invoice}/payments', [InvoiceController::class, 'payment'])->middleware('permission:payments.manage');
            Route::get('financial/reports', [ReportController::class, 'show'])->defaults('type', 'financial');

            // Reports
            Route::get('reports/{type}', [ReportController::class, 'show'])->whereIn('type', ['deliveries', 'fleet', 'drivers', 'financial']);

            // Integration settings (company admin)
            Route::middleware('permission:integrations.manage')->prefix('integrations')->group(function () {
                Route::get('/', [IntegrationSettingsController::class, 'index']);
                Route::post('clients', [IntegrationSettingsController::class, 'storeClient']);
                Route::post('clients/{client}/rotate', [IntegrationSettingsController::class, 'rotateClient']);
                Route::delete('clients/{client}', [IntegrationSettingsController::class, 'destroyClient']);
                Route::post('webhooks', [IntegrationSettingsController::class, 'storeWebhook']);
                Route::delete('webhooks/{webhook}', [IntegrationSettingsController::class, 'destroyWebhook']);
                Route::get('webhook-deliveries', [IntegrationSettingsController::class, 'deliveries']);
                Route::post('webhook-deliveries/{webhookDelivery}/retry', [IntegrationSettingsController::class, 'retry']);
            });
        });
    });
});

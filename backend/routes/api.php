<?php

use App\Http\Controllers\Api\V1\Auth\AuthController;
use App\Http\Controllers\Api\V1\Company\CompanyController;
use App\Http\Controllers\Api\V1\System\HealthController;
use Illuminate\Support\Facades\Route;

/*
| FleetFlow API v1. Middleware layers:
|   auth:sanctum → active → tenant (company resolved server-side) → permission:*
*/

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::get('health', HealthController::class)->name('health');

    // Auth (guests)
    Route::prefix('auth')->middleware('throttle:auth')->group(function () {
        Route::post('register', [AuthController::class, 'register'])->name('auth.register');
        Route::post('login', [AuthController::class, 'login'])->name('auth.login');
    });

    Route::middleware(['auth:sanctum', 'active'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        // Works with or without a workspace (onboarding needs it before one exists).
        Route::get('auth/me', [AuthController::class, 'me'])->middleware('tenant:optional')->name('auth.me');

        // Onboarding: create a company workspace (no tenant yet).
        Route::post('companies', [CompanyController::class, 'store'])->name('companies.store');
        Route::post('companies/{publicId}/switch', [CompanyController::class, 'switch'])->name('companies.switch');

        // Everything below runs inside a resolved, operational tenant.
        Route::middleware('tenant')->group(function () {
            Route::get('companies/current', [CompanyController::class, 'current'])->name('companies.current');
            Route::put('companies/current', [CompanyController::class, 'update'])->name('companies.update');
        });
    });
});

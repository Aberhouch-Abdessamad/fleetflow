<?php

use App\Exceptions\ApiExceptionRenderer;
use App\Http\Middleware\EnsureSuperAdmin;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\ResolveTenant;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Sanctum SPA auth: SANCTUM_STATEFUL_DOMAINS get cookie sessions + CSRF,
        // everything else (driver app, integrations) uses bearer tokens.
        $middleware->statefulApi();
        $middleware->throttleApi('api');

        $middleware->alias([
            'active' => EnsureUserIsActive::class,
            'tenant' => ResolveTenant::class,
            'super_admin' => EnsureSuperAdmin::class,
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
        ]);

        // Tenant must be resolved before Spatie permission checks run.
        $middleware->priority([
            StartSession::class,
            Authenticate::class,
            ResolveTenant::class,
            PermissionMiddleware::class,
            RoleMiddleware::class,
            SubstituteBindings::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => ApiExceptionRenderer::shouldHandle($request) || $request->expectsJson()
        );
        $exceptions->render(fn (Throwable $e, Request $request) => ApiExceptionRenderer::render($e, $request));
    })->create();

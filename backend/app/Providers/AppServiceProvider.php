<?php

namespace App\Providers;

use App\Models\Company;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job.
        $this->app->scoped(TenantContext::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        JsonResource::withoutWrapping();

        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
        ]);

        // Super admins pass platform-level gates only; tenant permissions are
        // never bypassed implicitly (audit requirement, spec §21).
        Gate::before(fn (User $user, string $ability) => str_starts_with($ability, 'platform.') && $user->is_super_admin ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(10)->by('ip:'.$request->ip()),
            Limit::perMinute(5)->by('email:'.strtolower((string) $request->input('email'))),
        ]);

        // GPS pings: generous but bounded per user (spec §13, §29).
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        // Public tracking links.
        RateLimiter::for('public-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

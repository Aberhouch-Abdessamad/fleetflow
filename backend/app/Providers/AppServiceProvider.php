<?php

namespace App\Providers;

use App\Models\ApiClient;
use App\Models\Company;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\DeliveryAssignment;
use App\Models\Driver;
use App\Models\Expense;
use App\Models\FuelRecord;
use App\Models\Invitation;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceReminder;
use App\Models\Route;
use App\Models\SubscriptionPlan;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\WebhookEndpoint;
use App\Notifications\Channels\TenantDatabaseChannel;
use App\Services\Maps\GoogleMapsProvider;
use App\Services\Maps\MapboxProvider;
use App\Services\Maps\MapsProvider;
use App\Services\Maps\NullMapsProvider;
use App\Services\Maps\OsmMapsProvider;
use App\Support\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One tenant context per request / job.
        $this->app->scoped(TenantContext::class);

        // Maps provider abstraction — swap via MAPS_PROVIDER without touching business logic.
        // osm (default) needs no API key: geocoding via OpenStreetMap Nominatim, but no routing;
        // mapbox/google add road routing + optimisation; "none" disables everything (503).
        $this->app->singleton(MapsProvider::class, fn () => match (strtolower((string) config('fleetflow.maps.provider'))) {
            'mapbox' => new MapboxProvider((string) config('fleetflow.maps.mapbox_token')),
            'google' => new GoogleMapsProvider((string) config('fleetflow.maps.google_key')),
            'osm' => new OsmMapsProvider((string) config('fleetflow.maps.osrm_url')),
            'none', 'disabled', 'null' => new NullMapsProvider,
            default => new OsmMapsProvider((string) config('fleetflow.maps.osrm_url')),
        });

        // Store company_id with database notifications.
        $this->app->bind(DatabaseChannel::class, TenantDatabaseChannel::class);
    }

    public function boot(): void
    {
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
        JsonResource::withoutWrapping();

        Relation::enforceMorphMap([
            'user' => User::class,
            'company' => Company::class,
            'api_client' => ApiClient::class,
            'vehicle' => Vehicle::class,
            'driver' => Driver::class,
            'customer' => Customer::class,
            'delivery' => Delivery::class,
            'route' => Route::class,
            'maintenance' => MaintenanceRecord::class,
            'fuel_record' => FuelRecord::class,
            'expense' => Expense::class,
            'invoice' => Invoice::class,
            'invitation' => Invitation::class,
            'plan' => SubscriptionPlan::class,
            'webhook_endpoint' => WebhookEndpoint::class,
        ]);
        \Illuminate\Support\Facades\Route::model('maintenance', MaintenanceRecord::class);
        \Illuminate\Support\Facades\Route::model('reminder', MaintenanceReminder::class);
        \Illuminate\Support\Facades\Route::model('client', ApiClient::class);
        \Illuminate\Support\Facades\Route::model('webhook', WebhookEndpoint::class);
        \Illuminate\Support\Facades\Route::model('assignment', DeliveryAssignment::class);
        \Illuminate\Support\Facades\Route::model('plan', SubscriptionPlan::class);

        // Super admins pass platform-level gates only; tenant permissions are
        // never bypassed implicitly (audit requirement, spec §21).
        Gate::before(fn (User $user, string $ability) => str_starts_with($ability, 'platform.') && $user->is_super_admin ? true : null);

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(300)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute(app()->isLocal() ? 60 : 10)->by('ip:'.$request->ip()),
            Limit::perMinute(app()->isLocal() ? 30 : 5)->by('email:'.strtolower((string) $request->input('email'))),
        ]);

        // GPS pings: generous but bounded per user (spec §13, §29).
        RateLimiter::for('tracking', fn (Request $request) => Limit::perMinute(60)->by($request->user()?->id ?: $request->ip()));

        RateLimiter::for('sensitive', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('integrations', fn (Request $request) => Limit::perMinute(300)->by('client:'.($request->user()?->getKey() ?? $request->ip())));

        // Public tracking links.
        RateLimiter::for('public-tracking', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));
    }
}

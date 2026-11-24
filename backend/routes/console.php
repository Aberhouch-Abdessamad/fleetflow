<?php

use App\Enums\SubscriptionStatus;
use App\Jobs\SendWebhook;
use App\Models\Company;
use App\Models\CompanySubscription;
use App\Models\VehicleLocation;
use App\Models\WebhookDelivery;
use App\Services\Maintenance\ReminderService;
use App\Services\Tenant\CompanySettings;
use App\Support\RoleRegistry;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('fleetflow:sync-roles', function () {
    RoleRegistry::sync();
    $this->info('Roles and permissions synced from config/fleetflow.php.');
})->purpose('Sync the FleetFlow role → permission matrix into the database');

Artisan::command('fleetflow:reminders', function (ReminderService $reminders) {
    $this->info('Reminders created: '.$reminders->runForAll());
})->purpose('Create maintenance / document-expiry reminders and notify users');

Artisan::command('fleetflow:prune-locations', function (CompanySettings $settings) {
    $total = 0;
    Company::query()->each(function (Company $c) use ($settings, &$total) {
        $days = (int) $settings->get($c, 'location_retention_days');
        do {
            $deleted = VehicleLocation::withoutGlobalScope('company')->where('company_id', $c->id)
                ->where('recorded_at', '<', now()->subDays($days))->limit(5000)->delete();
            $total += $deleted;
        } while ($deleted > 0);
    });
    $this->info("Pruned {$total} location points.");
})->purpose('Apply per-company GPS retention policy');

Artisan::command('fleetflow:retry-webhooks', function () {
    WebhookDelivery::withoutGlobalScope('company')->where('status', 'pending')->where('next_attempt_at', '<=', now())
        ->where('attempts', '>', 0)->limit(200)->pluck('id')->each(fn ($id) => SendWebhook::dispatch($id));
})->purpose('Retry failed webhook deliveries with backoff');

Artisan::command('fleetflow:expire-subscriptions', function () {
    $n = CompanySubscription::whereIn('status', ['trialing', 'active'])->get()->reject->isCurrent()
        ->each(fn ($s) => $s->forceFill(['status' => SubscriptionStatus::Expired])->save())->count();
    $this->info("Expired {$n} subscriptions.");
})->purpose('Mark ended trials / subscriptions as expired');

Schedule::call(fn () => cache()->put('scheduler:heartbeat', now()->toIso8601String(), 3600))->everyMinute()->name('heartbeat');
Schedule::command('fleetflow:reminders')->dailyAt('06:00')->withoutOverlapping();
Schedule::command('fleetflow:prune-locations')->dailyAt('02:30')->withoutOverlapping();
Schedule::command('fleetflow:retry-webhooks')->everyMinute()->withoutOverlapping();
Schedule::command('fleetflow:expire-subscriptions')->hourly();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();

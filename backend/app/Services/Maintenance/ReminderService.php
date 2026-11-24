<?php

namespace App\Services\Maintenance;

use App\Models\Company;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\MaintenanceReminder;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Notifications\DocumentExpiringNotification;
use App\Notifications\MaintenanceNotification;
use App\Services\Notify;
use App\Services\Tenant\CompanySettings;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Carbon;

/**
 * Daily scan (Laravel Scheduler) creating idempotent reminders and notifying
 * authorised users about approaching / overdue services and expiring documents.
 */
class ReminderService
{
    public function __construct(private readonly CompanySettings $settings, private readonly TenantContext $tenant) {}

    public function runForAll(): int
    {
        $count = 0;
        Company::where('status', 'active')->each(function (Company $c) use (&$count) {
            $count += $this->tenant->runAs($c, fn () => $this->run($c));
        });

        return $count;
    }

    public function run(Company $company): int
    {
        $days = (int) $this->settings->get($company, 'maintenance_reminder_days');
        $docDays = (int) $this->settings->get($company, 'document_expiry_warning_days');
        $today = now()->startOfDay();
        $created = 0;

        foreach (Vehicle::whereNotIn('status', ['inactive'])->get() as $v) {
            if ($v->next_service_date && $v->next_service_date->lte($today->copy()->addDays($days))) {
                $overdue = $v->next_service_date->lt($today);
                $created += $this->remind($company, $v, $overdue ? 'service_overdue' : 'service_due', null, null, $v->next_service_date, null,
                    $overdue ? "Service overdue: {$v->registration_number}" : "Service due: {$v->registration_number}",
                    'Scheduled for '.$v->next_service_date->toFormattedDateString(), $overdue ? 'danger' : 'warning');
            }
            if ($v->next_service_odometer && $v->odometer >= $v->next_service_odometer - 500) {
                $created += $this->remind($company, $v, 'odometer_due', null, null, null, $v->next_service_odometer,
                    "Service mileage reached: {$v->registration_number}", "Odometer {$v->odometer} km / service at {$v->next_service_odometer} km");
            }
            foreach (['insurance_expiry_date' => 'Insurance', 'registration_expiry_date' => 'Registration'] as $field => $label) {
                if ($v->{$field} && $v->{$field}->lte($today->copy()->addDays($docDays))) {
                    $created += $this->remind($company, $v, 'document_expiring', 'vehicle_'.$field, $v->id, $v->{$field}, null,
                        "{$label} expiring: {$v->registration_number}", 'Expires '.$v->{$field}->toFormattedDateString());
                }
            }
        }

        foreach (VehicleDocument::with('vehicle')->whereNotNull('expiry_date')->where('expiry_date', '<=', $today->copy()->addDays($docDays))->get() as $doc) {
            if ($doc->vehicle) {
                $created += $this->remind($company, $doc->vehicle, 'document_expiring', 'vehicle_document', $doc->id, $doc->expiry_date, null,
                    "Document expiring: {$doc->vehicle->registration_number}", ucfirst(str_replace('_', ' ', $doc->document_type)).' expires '.$doc->expiry_date->toFormattedDateString());
                if ($doc->expiry_date->lt($today) && $doc->status !== 'expired') {
                    $doc->forceFill(['status' => 'expired'])->save();
                }
            }
        }

        // Driver licences & documents → notify fleet managers (no vehicle reminder row).
        $driverDocs = DriverDocument::with('driver')->whereNotNull('expiry_date')->where('expiry_date', '<=', $today->copy()->addDays($docDays))->get();
        foreach (Driver::where('status', 'active')->where('license_expiry_date', '<=', $today->copy()->addDays($docDays))->get() as $d) {
            $this->notifyOnce($company, "license:{$d->id}:{$d->license_expiry_date->toDateString()}", new DocumentExpiringNotification(
                $company->id, "Driver licence expiring: {$d->fullName()}", 'Expires '.$d->license_expiry_date->toFormattedDateString(), "/app/drivers/{$d->id}"
            ));
        }
        foreach ($driverDocs as $doc) {
            if ($doc->driver) {
                $this->notifyOnce($company, "ddoc:{$doc->id}:{$doc->expiry_date->toDateString()}", new DocumentExpiringNotification(
                    $company->id, "Driver document expiring: {$doc->driver->fullName()}", ucfirst($doc->document_type).' expires '.$doc->expiry_date->toFormattedDateString(), "/app/drivers/{$doc->driver_id}"
                ));
            }
        }

        return $created;
    }

    private function remind(Company $company, Vehicle $v, string $type, ?string $subjectType, ?int $subjectId, ?Carbon $due, ?int $odo, string $title, string $body, string $tone = 'warning'): int
    {
        $reminder = MaintenanceReminder::where('vehicle_id', $v->id)->where('reminder_type', $type)
            ->where('subject_type', $subjectType)->where('subject_id', $subjectId)
            ->when($due, fn ($q) => $q->whereDate('due_date', $due->toDateString()), fn ($q) => $q->whereNull('due_date'))
            ->when($odo, fn ($q) => $q->where('due_odometer', $odo))
            ->first()
            ?? MaintenanceReminder::create([
                'vehicle_id' => $v->id, 'reminder_type' => $type, 'subject_type' => $subjectType, 'subject_id' => $subjectId,
                'due_date' => $due?->toDateString(), 'due_odometer' => $odo, 'status' => 'open',
            ]);

        if ($reminder->wasRecentlyCreated && $reminder->status === 'open') {
            $notification = $type === 'document_expiring'
                ? new DocumentExpiringNotification($company->id, $title, $body, "/app/vehicles/{$v->id}")
                : new MaintenanceNotification($company->id, $v->id, $title, $body, $tone);
            Notify::companyUsersWith($company, 'maintenance.manage', $notification);
            $reminder->forceFill(['notified_at' => now()])->save();

            return 1;
        }

        return 0;
    }

    private function notifyOnce(Company $company, string $key, DocumentExpiringNotification $n): void
    {
        if (cache()->add("reminder:{$company->id}:{$key}", 1, now()->addDays(7))) {
            Notify::companyUsersWith($company, 'drivers.manage', $n);
        }
    }
}

<?php

namespace App\Services\Driver;

use App\Enums\DeliveryStatus;
use App\Enums\DriverAvailability;
use App\Enums\DriverStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverAvailabilityRecord;
use App\Models\DriverDocument;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class DriverService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(Company $company, array $data, ?UploadedFile $photo = null): Driver
    {
        return DB::transaction(function () use ($company, $data, $photo) {
            Company::whereKey($company->id)->lockForUpdate()->first();
            $this->subscriptions->assertCanAdd($company, 'drivers');

            $driver = new Driver(fillable(Driver::class, $data));
            if ($photo) {
                $driver->photo_path = Uploads::store($photo, $company->id, 'drivers');
            }
            $driver->save();
            $this->logger->log('driver.created', $driver, ['name' => $driver->fullName()]);

            return $driver;
        });
    }

    public function update(Driver $driver, array $data, ?UploadedFile $photo = null): Driver
    {
        $driver->fill(fillable(Driver::class, $data));
        if ($photo) {
            $driver->photo_path = Uploads::store($photo, $driver->company_id, 'drivers');
        }
        $driver->save();
        $this->logger->log('driver.updated', $driver, ['fields' => array_keys($driver->getChanges())]);

        return $driver;
    }

    public function setStatus(Driver $driver, DriverStatus $status): Driver
    {
        if ($status !== DriverStatus::Active && $this->activeDeliveries($driver) > 0) {
            throw new DomainException('Reassign this driver\'s active deliveries first.', 'driver_busy');
        }
        DB::transaction(function () use ($driver, $status) {
            $driver->forceFill(['status' => $status])->save();
            if ($status !== DriverStatus::Active) {
                $driver->vehicleAssignments()->whereNull('unassigned_at')->get()->each(fn ($a) => $a->forceFill([
                    'unassigned_at' => now(), 'active_vehicle_id' => null, 'active_driver_id' => null,
                ])->save());
            }
        });
        $this->logger->log('driver.status_changed', $driver, ['status' => $status->value]);

        return $driver;
    }

    public function archive(Driver $driver): void
    {
        if ($this->activeDeliveries($driver) > 0) {
            throw new DomainException('This driver has active deliveries.', 'driver_busy');
        }
        $this->setStatus($driver, DriverStatus::Inactive);
        $driver->delete();
        $this->logger->log('driver.archived', $driver);
    }

    /** Records availability with history (time off, status change). */
    public function setAvailability(Driver $driver, DriverAvailability $status, ?User $by, ?string $startsAt = null, ?string $endsAt = null, ?string $reason = null): Driver
    {
        if (in_array($status, [DriverAvailability::OffDuty, DriverAvailability::OnLeave], true) && $this->activeDeliveries($driver) > 0 && ! $startsAt) {
            throw new DomainException('The driver has active deliveries. Reassign them before going off duty.', 'driver_busy');
        }

        return DB::transaction(function () use ($driver, $status, $by, $startsAt, $endsAt, $reason) {
            $start = $startsAt ? now()->parse($startsAt) : now();
            DriverAvailabilityRecord::where('driver_id', $driver->id)->whereNull('ends_at')->where('starts_at', '<=', $start)
                ->update(['ends_at' => $start]);
            DriverAvailabilityRecord::create([
                'driver_id' => $driver->id, 'status' => $status, 'starts_at' => $start, 'ends_at' => $endsAt,
                'reason' => $reason, 'recorded_by' => $by?->id,
            ]);
            if ($start->lte(now())) {
                $driver->forceFill(['availability' => $status])->save();
            }

            return $driver;
        });
    }

    public function addDocument(Driver $driver, array $data, ?UploadedFile $file): DriverDocument
    {
        $doc = new DriverDocument(fillable(DriverDocument::class, $data));
        $doc->driver_id = $driver->id;
        if ($file) {
            $doc->file_path = Uploads::store($file, $driver->company_id, 'driver-documents');
            $doc->original_name = mb_substr($file->getClientOriginalName(), 0, 200);
        }
        $doc->status = $doc->expiry_date && $doc->expiry_date->isPast() ? 'expired' : 'valid';
        $doc->save();
        if ($doc->document_type === 'license' && $doc->expiry_date) {
            $driver->forceFill(['license_expiry_date' => $doc->expiry_date])->save();
        }
        $this->logger->log('driver.document_added', $driver, ['type' => $doc->document_type]);

        return $doc;
    }

    public function activeDeliveries(Driver $driver): int
    {
        return Delivery::where('assigned_driver_id', $driver->id)->whereIn('status', DeliveryStatus::activeValues())->count();
    }
}

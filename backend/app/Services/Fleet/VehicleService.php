<?php

namespace App\Services\Fleet;

use App\Enums\DeliveryStatus;
use App\Enums\VehicleStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\ActivityLogger;
use App\Services\Subscription\SubscriptionService;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VehicleService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly VehicleAvailabilityService $availability,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(Company $company, array $data, ?UploadedFile $image = null): Vehicle
    {
        return DB::transaction(function () use ($company, $data, $image) {
            // Lock the company row so concurrent creates can't overshoot the plan limit.
            Company::whereKey($company->id)->lockForUpdate()->first();
            $this->subscriptions->assertCanAdd($company, 'vehicles');

            $vehicle = new Vehicle(fillable(Vehicle::class, $data));
            if ($image) {
                $vehicle->image_path = Uploads::store($image, $company->id, 'vehicles');
            }
            $vehicle->save();
            $this->logger->log('vehicle.created', $vehicle, ['registration' => $vehicle->registration_number]);

            return $vehicle;
        });
    }

    public function update(Vehicle $vehicle, array $data, ?UploadedFile $image = null): Vehicle
    {
        $vehicle->fill(fillable(Vehicle::class, $data));
        if ($image) {
            $old = $vehicle->image_path;
            $vehicle->image_path = Uploads::store($image, $vehicle->company_id, 'vehicles');
            if ($old) {
                Storage::disk(Uploads::DISK)->delete($old);
            }
        }
        $vehicle->save();
        $this->logger->log('vehicle.updated', $vehicle, ['fields' => array_keys($vehicle->getChanges())]);

        return $vehicle;
    }

    /** Managers may only set manual states; operational states are derived. */
    public function setStatus(Vehicle $vehicle, VehicleStatus $status, ?string $reason = null): Vehicle
    {
        if (! in_array($status, [VehicleStatus::Available, VehicleStatus::OutOfService, VehicleStatus::Inactive], true)) {
            throw new DomainException('This status is set automatically by assignments, deliveries and maintenance.', 'status_not_manual');
        }
        if ($status !== VehicleStatus::Available && $this->hasActiveDeliveries($vehicle)) {
            throw new DomainException('Reassign or complete the active deliveries on this vehicle first.', 'vehicle_busy');
        }

        $vehicle->forceFill(['status' => $status])->save();
        if ($status === VehicleStatus::Available) {
            $this->availability->refresh($vehicle);
        }
        $this->logger->log('vehicle.status_changed', $vehicle, ['status' => $vehicle->status->value, 'reason' => $reason]);

        return $vehicle;
    }

    public function archive(Vehicle $vehicle): void
    {
        if ($this->hasActiveDeliveries($vehicle)) {
            throw new DomainException('This vehicle has active deliveries and cannot be archived.', 'vehicle_busy');
        }
        DB::transaction(function () use ($vehicle) {
            $vehicle->assignments()->whereNull('unassigned_at')->update([
                'unassigned_at' => now(), 'active_vehicle_id' => null, 'active_driver_id' => null,
            ]);
            $vehicle->delete();
            $this->logger->log('vehicle.archived', $vehicle);
        });
    }

    public function addDocument(Vehicle $vehicle, array $data, ?UploadedFile $file): VehicleDocument
    {
        $doc = new VehicleDocument(fillable(VehicleDocument::class, $data));
        $doc->vehicle_id = $vehicle->id;
        if ($file) {
            $doc->file_path = Uploads::store($file, $vehicle->company_id, 'vehicle-documents');
            $doc->original_name = mb_substr($file->getClientOriginalName(), 0, 200);
        }
        $doc->status = $doc->expiry_date && $doc->expiry_date->isPast() ? 'expired' : 'valid';
        $doc->save();

        // Keep headline expiry dates on the vehicle in sync.
        $map = ['insurance' => 'insurance_expiry_date', 'registration' => 'registration_expiry_date'];
        if (isset($map[$doc->document_type]) && $doc->expiry_date) {
            $vehicle->forceFill([$map[$doc->document_type] => $doc->expiry_date])->save();
        }
        $this->logger->log('vehicle.document_added', $vehicle, ['type' => $doc->document_type]);

        return $doc;
    }

    private function hasActiveDeliveries(Vehicle $vehicle): bool
    {
        return Delivery::where('assigned_vehicle_id', $vehicle->id)->whereIn('status', DeliveryStatus::activeValues())->exists();
    }
}

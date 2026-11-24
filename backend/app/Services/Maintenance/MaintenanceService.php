<?php

namespace App\Services\Maintenance;

use App\Enums\DeliveryStatus;
use App\Enums\ExpenseCategory;
use App\Enums\MaintenanceStatus;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\MaintenanceRecord;
use App\Models\MaintenanceReminder;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use App\Services\Expense\ExpenseService;
use App\Services\Fleet\VehicleAvailabilityService;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class MaintenanceService
{
    public function __construct(
        private readonly VehicleAvailabilityService $availability,
        private readonly ExpenseService $expenses,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(array $data, User $by, ?UploadedFile $invoice = null): MaintenanceRecord
    {
        return DB::transaction(function () use ($data, $by, $invoice) {
            $record = new MaintenanceRecord(fillable(MaintenanceRecord::class, $data));
            $record->created_by = $by->id;
            $record->cost = Money::fromCents(max(0, Money::toCents($data['cost'] ?? 0)));
            if ($invoice) {
                $record->invoice_path = Uploads::store($invoice, $record->vehicle->company_id, 'maintenance');
            }
            $record->save();
            $this->logger->log('maintenance.scheduled', $record, ['vehicle_id' => $record->vehicle_id, 'cost' => $record->cost]);

            $status = MaintenanceStatus::tryFrom($data['status'] ?? 'scheduled') ?? MaintenanceStatus::Scheduled;
            if ($status === MaintenanceStatus::InProgress) {
                $this->start($record);
            } elseif ($status === MaintenanceStatus::Completed) {
                $this->complete($record, $data, $by);
            }

            return $record->fresh('vehicle');
        });
    }

    public function update(MaintenanceRecord $record, array $data, User $by, ?UploadedFile $invoice = null): MaintenanceRecord
    {
        if ($record->status === MaintenanceStatus::Completed && isset($data['cost']) && Money::cmp($data['cost'], $record->cost) !== 0) {
            throw new DomainException('Completed maintenance cost is locked (an expense was recorded). Adjust it through expenses.', 'record_locked');
        }
        $record->fill(fillable(MaintenanceRecord::class, $data));
        if (isset($data['cost'])) {
            $record->cost = Money::fromCents(max(0, Money::toCents($data['cost'])));
        }
        if ($invoice) {
            $record->invoice_path = Uploads::store($invoice, $record->company_id, 'maintenance');
        }
        $record->save();

        if (isset($data['status']) && $data['status'] !== $record->status->value) {
            match (MaintenanceStatus::from($data['status'])) {
                MaintenanceStatus::InProgress => $this->start($record),
                MaintenanceStatus::Completed => $this->complete($record, $data, $by),
                MaintenanceStatus::Cancelled => $this->cancel($record),
                MaintenanceStatus::Scheduled => throw new DomainException('A record cannot go back to scheduled.', 'invalid_transition'),
            };
        }

        return $record->fresh('vehicle');
    }

    /** Puts the vehicle in the workshop: it immediately stops being assignable. */
    public function start(MaintenanceRecord $record): MaintenanceRecord
    {
        if ($record->status !== MaintenanceStatus::Scheduled) {
            throw new DomainException('Only scheduled maintenance can be started.', 'invalid_transition');
        }
        if (Delivery::where('assigned_vehicle_id', $record->vehicle_id)->whereIn('status', [DeliveryStatus::PickedUp->value, DeliveryStatus::InTransit->value, DeliveryStatus::Arrived->value])->exists()) {
            throw new DomainException('The vehicle is currently on a delivery.', 'vehicle_on_road');
        }
        $record->forceFill(['status' => MaintenanceStatus::InProgress])->save();
        $this->availability->refresh($record->vehicle);
        $this->logger->log('maintenance.started', $record);

        return $record;
    }

    public function complete(MaintenanceRecord $record, array $data, User $by): MaintenanceRecord
    {
        if (! in_array($record->status, [MaintenanceStatus::Scheduled, MaintenanceStatus::InProgress], true)) {
            throw new DomainException('This maintenance is already closed.', 'invalid_transition');
        }

        return DB::transaction(function () use ($record, $data, $by) {
            $record->forceFill([
                'status' => MaintenanceStatus::Completed,
                'completed_date' => $data['completed_date'] ?? $record->completed_date ?? now()->toDateString(),
            ])->save();

            /** @var Vehicle $vehicle */
            $vehicle = $record->vehicle;
            $updates = ['last_service_date' => $record->completed_date];
            if ($record->next_service_date) {
                $updates['next_service_date'] = $record->next_service_date;
            }
            if ($record->next_service_odometer) {
                $updates['next_service_odometer'] = $record->next_service_odometer;
            }
            if ($record->odometer && $record->odometer > $vehicle->odometer) {
                $updates['odometer'] = $record->odometer;
            }
            $vehicle->forceFill($updates)->save();
            $this->availability->refresh($vehicle);

            MaintenanceReminder::where('vehicle_id', $vehicle->id)->whereIn('reminder_type', ['service_due', 'service_overdue', 'odometer_due'])
                ->where('status', 'open')->update(['status' => 'resolved']);

            if (Money::cmp($record->cost, 0) > 0) {
                $this->expenses->createFromSource('maintenance_record', $record->id, [
                    'vehicle_id' => $vehicle->id,
                    'category' => ExpenseCategory::Maintenance->value,
                    'description' => "Maintenance: {$record->title}",
                    'amount' => $record->cost,
                    'expense_date' => $record->completed_date->toDateString(),
                ], $by);
            }
            $this->logger->log('maintenance.completed', $record, ['cost' => $record->cost]);

            return $record;
        });
    }

    public function cancel(MaintenanceRecord $record): MaintenanceRecord
    {
        if ($record->status === MaintenanceStatus::Completed) {
            throw new DomainException('Completed maintenance cannot be cancelled.', 'invalid_transition');
        }
        $record->forceFill(['status' => MaintenanceStatus::Cancelled])->save();
        $this->availability->refresh($record->vehicle);
        $this->logger->log('maintenance.cancelled', $record);

        return $record;
    }

    public function delete(MaintenanceRecord $record): void
    {
        if ($record->status === MaintenanceStatus::Completed) {
            throw new DomainException('Completed maintenance is part of the vehicle history and cannot be deleted.', 'record_locked');
        }
        $vehicle = $record->vehicle;
        $record->delete();
        $this->availability->refresh($vehicle);
    }
}

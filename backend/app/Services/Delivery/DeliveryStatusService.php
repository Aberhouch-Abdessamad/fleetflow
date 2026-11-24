<?php

namespace App\Services\Delivery;

use App\Enums\AssignmentStatus;
use App\Enums\DeliveryStatus;
use App\Events\DeliveryCompleted;
use App\Events\DeliveryExceptionReported;
use App\Events\DeliveryStatusUpdated;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\DeliveryStatusHistory;
use App\Models\RouteStop;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Fleet\VehicleAvailabilityService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * The ONLY place delivery status changes. Validates the state machine,
 * records history, stamps timestamps, keeps vehicle availability consistent
 * and emits events after the transaction commits.
 */
class DeliveryStatusService
{
    public function __construct(
        private readonly VehicleAvailabilityService $availability,
        private readonly ActivityLogger $logger,
    ) {}

    public function recordInitial(Delivery $delivery, ?User $by, string $source): void
    {
        $delivery->statusHistory()->create([
            'previous_status' => null,
            'new_status' => $delivery->status->value,
            'changed_by' => $by?->id,
            'source' => $source,
            'notes' => 'Delivery created',
            'changed_at' => now(),
        ]);
        $this->dispatchEvents($delivery, null);
    }

    public function transition(
        Delivery $delivery,
        DeliveryStatus $to,
        ?User $by,
        ?string $notes = null,
        ?float $lat = null,
        ?float $lng = null,
        string $source = 'user',
        array $context = [],
    ): Delivery {
        return DB::transaction(function () use ($delivery, $to, $by, $notes, $lat, $lng, $source, $context) {
            // Row lock: two concurrent status updates can't both pass validation.
            $fresh = Delivery::whereKey($delivery->id)->lockForUpdate()->firstOrFail();
            $from = $fresh->status;

            if (! $from->canTransitionTo($to)) {
                throw new DomainException("Cannot change a delivery from {$from->label()} to {$to->label()}.", 'invalid_transition');
            }

            $this->guard($fresh, $to, $notes, $context);

            $attrs = ['status' => $to] + array_intersect_key($delivery->getDirty(), array_flip(['cancellation_reason', 'cancelled_at']));
            match ($to) {
                DeliveryStatus::Accepted => $attrs['accepted_at'] = now(),
                DeliveryStatus::PickedUp => $attrs['actual_pickup_at'] = now(),
                DeliveryStatus::Delivered => $attrs['actual_delivery_at'] = now(),
                DeliveryStatus::Failed => $attrs['failure_reason'] = $notes,
                DeliveryStatus::Pending => $attrs += ['assigned_driver_id' => null, 'assigned_vehicle_id' => null, 'assigned_at' => null, 'accepted_at' => null],
                default => null,
            };
            if ($to === DeliveryStatus::Delivered && Money::cmp($fresh->cod_amount, 0) > 0) {
                $collected = $context['cod_collected_amount'] ?? null;
                if ($collected === null) {
                    throw new DomainException('Confirm the cash-on-delivery amount collected.', 'cod_required');
                }
                $attrs['cod_collected_amount'] = Money::fromCents(Money::toCents((string) $collected));
                $attrs['cod_collected_at'] = now();
            }

            $vehicleId = $fresh->assigned_vehicle_id;
            $fresh->forceFill($attrs)->save();

            DeliveryStatusHistory::create([
                'delivery_id' => $fresh->id,
                'previous_status' => $from->value,
                'new_status' => $to->value,
                'changed_by' => $by?->id,
                'source' => $source,
                'notes' => $notes,
                'latitude' => $lat,
                'longitude' => $lng,
                'changed_at' => now(),
            ]);

            if ($to->isTerminal() || $to === DeliveryStatus::Failed) {
                $fresh->assignments()->where('status', AssignmentStatus::Accepted)->update([
                    'status' => $to === DeliveryStatus::Delivered ? AssignmentStatus::Completed->value : AssignmentStatus::Cancelled->value,
                ]);
            }
            if ($fresh->route_id) {
                $stopStatus = match ($to) {
                    DeliveryStatus::Arrived => ['status' => 'arrived', 'actual_arrival' => now()],
                    DeliveryStatus::Delivered => ['status' => 'completed', 'completed_at' => now()],
                    DeliveryStatus::Failed, DeliveryStatus::Cancelled => ['status' => 'skipped'],
                    default => null,
                };
                if ($stopStatus) {
                    RouteStop::where('route_id', $fresh->route_id)->where('delivery_id', $fresh->id)->update($stopStatus);
                }
            }

            $this->availability->refreshId($vehicleId);
            $this->logger->log('delivery.status_changed', $fresh, ['from' => $from->value, 'to' => $to->value, 'source' => $source]);

            DB::afterCommit(fn () => $this->dispatchEvents($fresh->fresh(['driver']), $from));

            $delivery->setRawAttributes($fresh->getAttributes(), true);

            return $fresh;
        });
    }

    private function guard(Delivery $d, DeliveryStatus $to, ?string $notes, array $context): void
    {
        if (in_array($to, [DeliveryStatus::Failed, DeliveryStatus::Cancelled], true) && blank($notes)) {
            throw new DomainException('A reason is required.', 'reason_required');
        }
        if (in_array($to, [DeliveryStatus::PickedUp, DeliveryStatus::InTransit, DeliveryStatus::Arrived, DeliveryStatus::Delivered], true)
            && (! $d->assigned_driver_id || ! $d->assigned_vehicle_id)) {
            throw new DomainException('Assign a driver and vehicle first.', 'not_assigned');
        }
        if ($to === DeliveryStatus::Delivered) {
            if ($d->requires_proof && ! $d->proofs()->whereIn('proof_type', ['photo', 'signature'])->exists()) {
                throw new DomainException('Upload a photo or signature as proof of delivery first.', 'proof_required');
            }
            if ($d->otp_hash && ! $d->proofs()->where('proof_type', 'otp')->exists()) {
                throw new DomainException('Verify the delivery code given by the recipient first.', 'otp_required');
            }
        }
    }

    private function dispatchEvents(Delivery $d, ?DeliveryStatus $from): void
    {
        $company = $d->company;
        $payload = [
            'delivery_id' => $d->id,
            'reference' => $d->reference_number,
            'status' => $d->status->value,
            'previous_status' => $from?->value,
            'driver_id' => $d->assigned_driver_id,
            'vehicle_id' => $d->assigned_vehicle_id,
            'external_reference' => $d->external_reference,
            'failure_reason' => $d->failure_reason,
            'occurred_at' => now()->toIso8601String(),
        ];
        $extra = $d->assigned_driver_id ? ["driver.{$d->assigned_driver_id}"] : [];

        event(new DeliveryStatusUpdated($company->id, $company->public_id, $payload, $extra));
        if ($d->status === DeliveryStatus::Delivered) {
            event(new DeliveryCompleted($company->id, $company->public_id, $payload, $extra));
        }
        if ($d->status === DeliveryStatus::Failed) {
            event(new DeliveryExceptionReported($company->id, $company->public_id, $payload + ['type' => 'failed'], $extra));
        }
    }
}

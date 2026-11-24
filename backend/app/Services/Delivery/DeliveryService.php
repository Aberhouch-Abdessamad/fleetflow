<?php

namespace App\Services\Delivery;

use App\Enums\AssignmentStatus;
use App\Enums\DeliveryStatus;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Fleet\VehicleAvailabilityService;
use App\Services\Subscription\SubscriptionService;
use App\Services\Tenant\CompanySettings;
use App\Support\Geo;
use App\Support\Money;
use App\Support\Reference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DeliveryService
{
    public function __construct(
        private readonly DeliveryStatusService $status,
        private readonly SubscriptionService $subscriptions,
        private readonly CompanySettings $settings,
        private readonly VehicleAvailabilityService $availability,
        private readonly ActivityLogger $logger,
    ) {}

    /**
     * @param  array{external_source?:string,external_reference?:string}  $external
     * @return array{0: Delivery, 1: ?string} delivery and the plain OTP (only returned once, if generated)
     */
    public function create(Company $company, array $data, ?User $by, string $source = 'user', array $external = []): array
    {
        return DB::transaction(function () use ($company, $data, $by, $source, $external) {
            $this->subscriptions->assertCanAdd($company, 'monthly_deliveries');

            $delivery = new Delivery(fillable(Delivery::class, $data));
            $delivery->reference_number = Reference::unique('DEL', fn ($r) => Delivery::withTrashed()->where('reference_number', $r)->exists());
            $delivery->tracking_token = Str::random(48);
            $delivery->created_by = $by?->id;
            $delivery->external_source = $external['external_source'] ?? null;
            $delivery->external_reference = $external['external_reference'] ?? null;
            $delivery->status = ($data['as_draft'] ?? false) ? DeliveryStatus::Draft : DeliveryStatus::Pending;
            $delivery->requires_proof = (bool) ($data['requires_proof'] ?? $this->settings->get($company, 'require_proof_of_delivery'));
            $this->normaliseMoney($delivery);

            $otp = null;
            if ($data['require_otp'] ?? $this->settings->get($company, 'require_delivery_otp')) {
                $otp = (string) random_int(100000, 999999);
                $delivery->otp_hash = Hash::make($otp);
            }
            $delivery->distance_km = $this->straightLine($delivery);
            $delivery->save();

            $this->syncItems($delivery, $data['items'] ?? []);
            $this->status->recordInitial($delivery, $by, $source);
            $this->logger->log('delivery.created', $delivery, ['reference' => $delivery->reference_number, 'source' => $source]);

            return [$delivery->fresh(['items']), $otp];
        });
    }

    public function update(Delivery $delivery, array $data, User $by): Delivery
    {
        if (! in_array($delivery->status, [DeliveryStatus::Draft, DeliveryStatus::Pending, DeliveryStatus::Assigned, DeliveryStatus::Accepted], true)) {
            throw new DomainException('A delivery can no longer be edited once it has been picked up.', 'delivery_locked');
        }

        return DB::transaction(function () use ($delivery, $data, $by) {
            $delivery->fill(fillable(Delivery::class, $data));
            $this->normaliseMoney($delivery);
            $delivery->distance_km = $this->straightLine($delivery);
            $delivery->save();
            if (array_key_exists('items', $data)) {
                $this->syncItems($delivery, $data['items'] ?? []);
                // Weight changed: an assigned vehicle must still have room.
                if ($delivery->assigned_vehicle_id) {
                    $vehicle = $delivery->vehicle;
                    $load = $this->availability->activeLoad($vehicle, $delivery->id) + (float) $delivery->total_weight;
                    if ($vehicle->capacity > 0 && $load > (float) $vehicle->capacity) {
                        throw new DomainException('The updated items exceed the assigned vehicle capacity.', 'capacity_exceeded');
                    }
                }
            }
            if (($data['publish'] ?? false) && $delivery->status === DeliveryStatus::Draft) {
                $this->status->transition($delivery, DeliveryStatus::Pending, $by);
            }
            $this->logger->log('delivery.updated', $delivery, ['fields' => array_keys($delivery->getChanges())]);

            return $delivery->fresh(['items']);
        });
    }

    /** Cancellation rules: allowed until pickup; reason required; releases the assignment. */
    public function cancel(Delivery $delivery, ?User $by, string $reason, string $source = 'user'): Delivery
    {
        if (! $delivery->status->canTransitionTo(DeliveryStatus::Cancelled)) {
            throw new DomainException('Only deliveries that have not been picked up can be cancelled.', 'cancel_not_allowed');
        }

        return DB::transaction(function () use ($delivery, $by, $reason, $source) {
            $vehicleId = $delivery->assigned_vehicle_id;
            $delivery->assignments()->whereIn('status', ['pending', 'accepted'])->update(['status' => AssignmentStatus::Cancelled->value]);
            $delivery->forceFill(['cancellation_reason' => $reason, 'cancelled_at' => now()]);
            $this->status->transition($delivery, DeliveryStatus::Cancelled, $by, $reason, source: $source);
            $this->availability->refreshId($vehicleId);

            return $delivery;
        });
    }

    public function delete(Delivery $delivery): void
    {
        if (! in_array($delivery->status, [DeliveryStatus::Draft, DeliveryStatus::Pending, DeliveryStatus::Cancelled], true)) {
            throw new DomainException('Only draft, pending or cancelled deliveries can be deleted.', 'delete_not_allowed');
        }
        if ($delivery->invoiceItem()->exists()) {
            throw new DomainException('This delivery is on an invoice.', 'delivery_invoiced');
        }
        $delivery->delete();
        $this->logger->log('delivery.deleted', $delivery);
    }

    public function regenerateOtp(Delivery $delivery): string
    {
        $otp = (string) random_int(100000, 999999);
        $delivery->forceFill(['otp_hash' => Hash::make($otp)])->save();

        return $otp;
    }

    private function syncItems(Delivery $delivery, array $items): void
    {
        $delivery->items()->delete();
        $weight = 0.0;
        foreach ($items as $item) {
            $delivery->items()->create($item);
            $weight += ((float) ($item['weight'] ?? 0)) * (int) ($item['quantity'] ?? 1);
        }
        $delivery->forceFill(['total_weight' => round($weight, 2)])->save();
    }

    private function normaliseMoney(Delivery $delivery): void
    {
        $delivery->delivery_fee = Money::fromCents(max(0, Money::toCents($delivery->delivery_fee ?? 0)));
        $delivery->cod_amount = Money::fromCents(max(0, Money::toCents($delivery->cod_amount ?? 0)));
    }

    private function straightLine(Delivery $d): ?float
    {
        return Geo::valid($d->pickup_latitude, $d->pickup_longitude) && Geo::valid($d->delivery_latitude, $d->delivery_longitude)
            ? round(Geo::distanceKm($d->pickup_latitude, $d->pickup_longitude, $d->delivery_latitude, $d->delivery_longitude), 2)
            : null;
    }
}

<?php

namespace App\Services\Tracking;

use App\Enums\DeliveryStatus;
use App\Events\VehicleLocationUpdated;
use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverShift;
use App\Models\Geofence;
use App\Models\GeofenceEvent;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleLocation;
use App\Services\Subscription\SubscriptionService;
use App\Support\Geo;
use App\Support\Tenancy\TenantContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * GPS ingestion. Driver and vehicle identity are DERIVED from the authenticated
 * user and their current assignment — never taken from the payload.
 */
class LocationService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * @param  array{latitude:float,longitude:float,speed?:?float,heading?:?float,accuracy?:?float,recorded_at:string}  $p
     * @return array{status:'stored'|'duplicate'|'throttled', location:?VehicleLocation}
     */
    public function ingest(Company $company, User $user, array $p): array
    {
        $this->subscriptions->assertFeature($company, 'gps_tracking');

        $driver = Driver::where('user_id', $user->id)->first()
            ?? throw new DomainException('Only drivers can report locations.', 'not_a_driver', 403);
        if ($driver->status->value !== 'active') {
            throw new DomainException('Your driver profile is not active.', 'driver_inactive', 403);
        }

        $lat = (float) $p['latitude'];
        $lng = (float) $p['longitude'];
        if (! Geo::valid($lat, $lng)) {
            throw new DomainException('Invalid coordinates.', 'invalid_coordinates', 422);
        }
        $recordedAt = CarbonImmutable::parse($p['recorded_at'])->utc();
        if ($recordedAt->gt(now()->addMinutes(2))) {
            throw new DomainException('Location timestamp is in the future.', 'invalid_timestamp', 422);
        }
        if ($recordedAt->lt(now()->subHours(24))) {
            throw new DomainException('Location is too old to be accepted.', 'stale_location', 422);
        }

        // Location sharing is allowed while on shift or carrying an active delivery.
        $activeDelivery = Delivery::where('assigned_driver_id', $driver->id)
            ->whereIn('status', [DeliveryStatus::PickedUp, DeliveryStatus::InTransit, DeliveryStatus::Arrived, DeliveryStatus::Accepted])
            ->orderByRaw("case status when 'in_transit' then 0 when 'arrived' then 1 when 'picked_up' then 2 else 3 end")->first();
        $onShift = DriverShift::where('driver_id', $driver->id)->whereNull('ended_at')->exists();
        if (! $onShift && ! $activeDelivery) {
            throw new DomainException('Start your shift to share your location.', 'not_on_duty', 403);
        }

        $vehicleId = $activeDelivery?->assigned_vehicle_id ?? $driver->activeVehicleAssignment?->vehicle_id;

        // Server-side throttle: ignore pings closer together than the configured interval.
        $minInterval = (int) config('fleetflow.tracking.min_interval_seconds', 5);
        $key = "loc:last:{$driver->id}";
        $last = Cache::get($key);
        if ($last && abs($recordedAt->getTimestamp() - $last) < $minInterval) {
            return ['status' => 'throttled', 'location' => null];
        }

        try {
            $location = DB::transaction(function () use ($driver, $vehicleId, $activeDelivery, $lat, $lng, $p, $recordedAt) {
                $loc = VehicleLocation::create([
                    'vehicle_id' => $vehicleId,
                    'driver_id' => $driver->id,
                    'delivery_id' => $activeDelivery?->id,
                    'latitude' => $lat,
                    'longitude' => $lng,
                    'speed' => isset($p['speed']) ? max(0, (float) $p['speed']) : null,
                    'heading' => $p['heading'] ?? null,
                    'accuracy' => $p['accuracy'] ?? null,
                    'recorded_at' => $recordedAt,
                    'received_at' => now(),
                ]);
                if ($vehicleId) {
                    // Only move the "last position" forward in time (out-of-order pings are kept in history only).
                    Vehicle::whereKey($vehicleId)
                        ->where(fn ($q) => $q->whereNull('last_located_at')->orWhere('last_located_at', '<', $recordedAt))
                        ->update([
                            'last_latitude' => $lat, 'last_longitude' => $lng,
                            'last_speed' => $loc->speed, 'last_heading' => $loc->heading,
                            'last_located_at' => $recordedAt, 'last_driver_id' => $driver->id,
                        ]);
                }

                return $loc;
            });
        } catch (QueryException $e) {
            if (str_contains(strtolower($e->getMessage()), 'unique')) {
                return ['status' => 'duplicate', 'location' => null];
            }
            throw $e;
        }

        Cache::put($key, $recordedAt->getTimestamp(), 3600);

        if ($vehicleId) {
            $this->checkGeofences($vehicleId, $lat, $lng, $recordedAt);
        }

        $company = $driver->company;
        event(new VehicleLocationUpdated($company->id, $company->public_id, [
            'vehicle_id' => $vehicleId,
            'driver_id' => $driver->id,
            'delivery_id' => $activeDelivery?->id,
            'latitude' => $lat,
            'longitude' => $lng,
            'speed' => $location->speed,
            'heading' => $location->heading,
            'accuracy' => $location->accuracy,
            'recorded_at' => $recordedAt->toIso8601String(),
        ]));

        return ['status' => 'stored', 'location' => $location];
    }

    private function checkGeofences(int $vehicleId, float $lat, float $lng, CarbonImmutable $at): void
    {
        $fences = Cache::remember('geofences:'.app(TenantContext::class)->id(), 60, fn () => Geofence::where('status', 'active')->get());
        foreach ($fences as $f) {
            $inside = Geo::distanceKm($lat, $lng, $f->latitude, $f->longitude) * 1000 <= $f->radius;
            $last = GeofenceEvent::where('geofence_id', $f->id)->where('vehicle_id', $vehicleId)->latest('occurred_at')->value('event_type');
            $wasInside = $last === 'enter';
            if ($inside !== $wasInside) {
                GeofenceEvent::create([
                    'geofence_id' => $f->id, 'vehicle_id' => $vehicleId,
                    'event_type' => $inside ? 'enter' : 'exit', 'occurred_at' => $at, 'metadata' => ['lat' => $lat, 'lng' => $lng],
                ]);
            }
        }
    }

    /** Live = updated within the stale window; otherwise "last known". */
    public static function freshness(?\DateTimeInterface $at): string
    {
        if (! $at) {
            return 'none';
        }

        return now()->diffInSeconds($at, true) <= (int) config('fleetflow.tracking.stale_after_seconds', 120) ? 'live' : 'stale';
    }
}

<?php

namespace App\Http\Controllers\Api\V1\Tracking;

use App\Enums\DeliveryStatus;
use App\Exceptions\DomainException;
use App\Http\Controllers\Controller;
use App\Models\Delivery;
use App\Models\RouteStop;
use App\Services\Tenant\CompanySettings;
use App\Services\Tracking\LocationService;
use App\Support\ApiResponse;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated tracking via an unguessable 48-char token.
 * Exposes ONLY customer-facing fields: no internal ids, no driver phone, no company internals.
 */
class PublicTrackingController extends Controller
{
    public function __construct(private readonly CompanySettings $settings, private readonly TenantContext $tenant) {}

    private function find(string $token): Delivery
    {
        if (strlen($token) !== 48 || ! ctype_alnum($token)) {
            throw new DomainException('Tracking link not found.', 'not_found', 404);
        }
        $d = Delivery::withoutGlobalScope('company')->with('company')->where('tracking_token', $token)->first();
        if (! $d || ! $d->company || ! $d->company->status->isOperational() || ! $this->settings->get($d->company, 'public_tracking_enabled')) {
            throw new DomainException('Tracking link not found.', 'not_found', 404);
        }
        $this->tenant->set($d->company);

        return $d;
    }

    public function show(string $token): JsonResponse
    {
        $d = $this->find($token);
        $company = $d->company;
        $s = $this->settings->all($company);
        $driver = $d->driver;
        $vehicle = $d->vehicle;
        $live = $s['tracking_show_live_position'] && $d->status->isOnRoad() && $vehicle?->last_located_at;

        // Visible milestones only (no internal notes / user names)
        $timeline = $d->statusHistory()->get()
            ->filter(fn ($h) => $h->previous_status !== $h->new_status && ! in_array($h->new_status, ['draft'], true))
            ->map(fn ($h) => ['status' => $h->new_status, 'at' => $h->changed_at->toIso8601String()])->values();

        $eta = null;
        if ($d->status->isActive()) {
            $eta = $d->route_id ? RouteStop::where('route_id', $d->route_id)->where('delivery_id', $d->id)->value('estimated_arrival') : null;
            $eta = $eta ? now()->parse($eta)->toIso8601String() : $d->scheduled_delivery_at?->toIso8601String();
        }

        return ApiResponse::ok([
            'reference' => $d->reference_number,
            'status' => $d->status->value,
            'company' => ['name' => $company->name, 'phone' => $company->phone],
            'recipient_name' => $d->recipient_name,
            'pickup' => ['address' => $d->pickup_address],
            'destination' => ['address' => $d->delivery_address, 'latitude' => $d->delivery_latitude, 'longitude' => $d->delivery_longitude],
            'scheduled_delivery_at' => $d->scheduled_delivery_at?->toIso8601String(),
            'estimated_arrival' => $eta,
            'delivered_at' => $d->actual_delivery_at?->toIso8601String(),
            'failure_reason' => $d->status === DeliveryStatus::Failed ? 'Delivery attempt unsuccessful' : null,
            'timeline' => $timeline,
            'driver' => $s['tracking_show_driver_name'] && $driver && $d->status->isActive() ? ['first_name' => $driver->first_name] : null,
            'live_position' => $live ? [
                'latitude' => $vehicle->last_latitude, 'longitude' => $vehicle->last_longitude,
                'recorded_at' => $vehicle->last_located_at->toIso8601String(),
                'freshness' => LocationService::freshness($vehicle->last_located_at),
            ] : null,
            'can_rate' => $d->status === DeliveryStatus::Delivered && $d->customer_rating === null,
            'rating' => $d->customer_rating,
        ]);
    }

    public function rate(Request $request, string $token): JsonResponse
    {
        $d = $this->find($token);
        $data = $request->validate(['rating' => ['required', 'integer', 'between:1,5'], 'comment' => ['nullable', 'string', 'max:500']]);
        if ($d->status !== DeliveryStatus::Delivered) {
            throw new DomainException('You can rate a delivery once it has been delivered.', 'not_delivered');
        }
        if ($d->customer_rating !== null) {
            throw new DomainException('This delivery has already been rated.', 'already_rated', 409);
        }
        $d->forceFill(['customer_rating' => $data['rating'], 'rating_comment' => $data['comment'] ?? null])->save();

        return ApiResponse::ok(null, 'Thank you for your feedback.');
    }
}

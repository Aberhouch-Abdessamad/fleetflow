<?php

namespace App\Services\Route;

use App\Enums\DeliveryStatus;
use App\Enums\RouteStatus;
use App\Exceptions\DomainException;
use App\Models\Delivery;
use App\Models\Route;
use App\Models\RouteOptimizationLog;
use App\Models\User;
use App\Models\VehicleLocation;
use App\Services\ActivityLogger;
use App\Services\Maps\MapsProvider;
use App\Services\Maps\MapsUnavailableException;
use App\Services\Subscription\SubscriptionService;
use App\Support\Geo;
use App\Support\Reference;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;

class RouteService
{
    public function __construct(
        private readonly MapsProvider $maps,
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityLogger $logger,
    ) {}

    public function create(array $data, User $by): Route
    {
        $this->subscriptions->assertFeature(app(TenantContext::class)->companyOrFail(), 'route_planning');

        return DB::transaction(function () use ($data, $by) {
            $route = new Route(fillable(Route::class, $data));
            $route->reference_number = Reference::unique('RTE', fn ($r) => Route::where('reference_number', $r)->exists());
            $route->created_by = $by->id;
            $route->save();
            $this->addStops($route, $data['delivery_ids'] ?? []);
            $this->logger->log('route.created', $route);

            return $this->recalculate($route);
        });
    }

    public function update(Route $route, array $data): Route
    {
        $this->assertEditable($route);
        $route->fill(fillable(Route::class, $data))->save();
        if (isset($data['stop_order'])) {
            $this->reorder($route, $data['stop_order']);
        }

        return $this->recalculate($route);
    }

    /** @param list<int> $deliveryIds */
    public function addStops(Route $route, array $deliveryIds): Route
    {
        $this->assertEditable($route);
        $deliveries = Delivery::whereIn('id', $deliveryIds)->get()->keyBy('id');
        $next = (int) $route->stops()->max('stop_order');

        foreach ($deliveryIds as $id) {
            $d = $deliveries[$id] ?? throw new DomainException('One of the deliveries was not found.', 'not_found', 404);
            if (in_array($d->status, [DeliveryStatus::Delivered, DeliveryStatus::Cancelled, DeliveryStatus::Returned, DeliveryStatus::Failed], true)) {
                throw new DomainException("{$d->reference_number} is {$d->status->label()} and cannot be routed.", 'delivery_closed');
            }
            if ($d->route_id && $d->route_id !== $route->id) {
                throw new DomainException("{$d->reference_number} is already on another route.", 'delivery_on_route');
            }
            if ($route->stops()->where('delivery_id', $d->id)->exists()) {
                continue;
            }
            $route->stops()->create([
                'delivery_id' => $d->id,
                'stop_order' => ++$next,
                'stop_type' => 'delivery',
                'address' => $d->delivery_address,
                'latitude' => $d->delivery_latitude,
                'longitude' => $d->delivery_longitude,
                'priority' => $d->priority->value,
            ]);
            $d->forceFill(['route_id' => $route->id])->save();
        }

        return $route;
    }

    public function removeStop(Route $route, int $stopId): Route
    {
        $this->assertEditable($route);
        $stop = $route->stops()->findOrFail($stopId);
        if ($stop->delivery_id) {
            Delivery::whereKey($stop->delivery_id)->update(['route_id' => null]);
        }
        $stop->delete();
        $this->reorder($route, $route->stops()->pluck('id')->all());

        return $this->recalculate($route);
    }

    /** @param list<int> $stopIds in the desired order */
    public function reorder(Route $route, array $stopIds): void
    {
        $existing = $route->stops()->pluck('id')->sort()->values()->all();
        $given = collect($stopIds)->map(fn ($i) => (int) $i)->sort()->values()->all();
        if ($existing !== $given) {
            throw new DomainException('The new order must contain exactly the route\'s stops.', 'invalid_order');
        }
        foreach (array_values($stopIds) as $i => $id) {
            $route->stops()->whereKey($id)->update(['stop_order' => $i + 1]);
        }
    }

    /**
     * Distance/duration: from the routing provider when configured; otherwise a
     * straight-line distance is stored and clearly flagged (distance_source = straight_line, no ETA).
     */
    public function recalculate(Route $route): Route
    {
        $points = $this->points($route);
        if (count($points) < 2) {
            $route->forceFill(['planned_distance' => null, 'estimated_duration' => null, 'geometry' => null, 'distance_source' => null])->save();

            return $route->load('stops.delivery');
        }

        if ($this->maps->routingAvailable()) {
            try {
                $r = $this->maps->route(array_column($points, 'pt'));
            } catch (MapsUnavailableException|ConnectionException) {
                // A best-effort public OSRM may be unreachable — never block route
                // creation; fall back to an honestly-labelled straight-line distance.
                $r = null;
            }
        } else {
            $r = null;
        }
        if ($r !== null) {
            $route->forceFill([
                'planned_distance' => $r['distance_km'], 'estimated_duration' => $r['duration_minutes'],
                'geometry' => $r['geometry'], 'distance_source' => $this->maps->name(),
            ])->save();
            // Per-stop ETAs from leg durations (only when route has a date / start time)
            $start = $route->route_date->isToday() ? now() : $route->route_date->copy()->setTime(8, 0);
            $elapsed = 0;
            foreach ($r['legs'] as $i => $leg) {
                $elapsed += $leg['duration_minutes'];
                $stopId = $points[$i + 1]['stop_id'] ?? null;
                if ($stopId) {
                    $route->stops()->whereKey($stopId)->update(['estimated_arrival' => $start->copy()->addMinutes($elapsed)]);
                }
            }
        } else {
            $route->forceFill([
                'planned_distance' => Geo::pathKm(array_column($points, 'pt')),
                'estimated_duration' => null, 'geometry' => null, 'distance_source' => 'straight_line',
            ])->save();
            $route->stops()->update(['estimated_arrival' => null]);
        }

        return $route->load('stops.delivery');
    }

    /** Real optimisation through the provider. Refuses (503) when none is configured. */
    public function optimize(Route $route, User $by): Route
    {
        $this->assertEditable($route);
        $this->subscriptions->assertFeature(app(TenantContext::class)->companyOrFail(), 'route_optimization');
        if (! $this->maps->routingAvailable()) {
            throw new MapsUnavailableException('Route optimisation needs a routing endpoint. Keep MAPS_PROVIDER=osm (set OSRM_URL) or configure mapbox/google.');
        }
        $points = $this->points($route);
        $stopPoints = array_values(array_filter($points, fn ($p) => $p['stop_id'] !== null));
        if (count($stopPoints) < 2) {
            throw new DomainException('Add at least two stops with coordinates to optimise.', 'not_enough_stops');
        }
        if (count($stopPoints) !== $route->stops()->count()) {
            throw new DomainException('Every stop needs coordinates before optimisation.', 'missing_coordinates');
        }

        // Urgent/high-priority stops are kept first, in their current order; the rest are optimised.
        $before = ['distance' => $route->distance_source === 'straight_line' ? null : $route->planned_distance, 'duration' => $route->estimated_duration];
        $original = $route->stops()->pluck('id')->all();

        $hasEnd = $route->end_latitude !== null;
        $result = $this->maps->optimize(array_column($points, 'pt'), $hasEnd);
        $optimizedStopIds = collect($result['order'])->map(fn ($i) => $points[$i]['stop_id'])->filter()->values();

        $priority = $route->stops()->whereIn('priority', ['urgent', 'high'])->orderBy('stop_order')->pluck('id');
        $newOrder = $priority->merge($optimizedStopIds->diff($priority))->values()->all();

        DB::transaction(function () use ($route, $newOrder, $original, $before, $by, $result) {
            $this->reorder($route, $newOrder);
            $this->recalculate($route);
            RouteOptimizationLog::create([
                'route_id' => $route->id,
                'provider' => $this->maps->name(),
                'original_sequence' => $original,
                'optimized_sequence' => $newOrder,
                'distance_before' => $before['distance'],
                'distance_after' => $route->planned_distance ?? $result['distance_km'],
                'duration_before' => $before['duration'],
                'duration_after' => $route->estimated_duration ?? $result['duration_minutes'],
                'requested_by' => $by->id,
                'optimized_at' => now(),
            ]);
        });
        $this->logger->log('route.optimized', $route, ['provider' => $this->maps->name()]);

        return $route->fresh(['stops.delivery', 'optimizationLogs']);
    }

    public function start(Route $route): Route
    {
        if ($route->status !== RouteStatus::Planned) {
            throw new DomainException('Only planned routes can be started.', 'invalid_transition');
        }
        if (! $route->driver_id || ! $route->vehicle_id) {
            throw new DomainException('Select a driver and vehicle before starting the route.', 'route_unassigned');
        }
        if (! $route->stops()->exists()) {
            throw new DomainException('Add stops before starting the route.', 'route_empty');
        }
        $route->forceFill(['status' => RouteStatus::InProgress, 'started_at' => now()])->save();
        $this->logger->log('route.started', $route);

        return $route->load('stops.delivery');
    }

    public function complete(Route $route): Route
    {
        if ($route->status !== RouteStatus::InProgress) {
            throw new DomainException('Only routes in progress can be completed.', 'invalid_transition');
        }
        $open = $route->stops()->whereIn('status', ['pending', 'arrived'])->count();
        if ($open > 0) {
            throw new DomainException("{$open} stop(s) are still open. Complete or skip them first.", 'stops_open');
        }
        $actual = $this->actualDistance($route);
        $route->forceFill([
            'status' => RouteStatus::Completed, 'completed_at' => now(),
            'actual_duration' => (int) $route->started_at->diffInMinutes(now()),
            'actual_distance' => $actual,
        ])->save();
        $this->logger->log('route.completed', $route);

        return $route->load('stops.delivery');
    }

    /** Actual distance from recorded GPS points between start and completion. */
    private function actualDistance(Route $route): ?float
    {
        if (! $route->driver_id) {
            return null;
        }
        $pts = VehicleLocation::where('driver_id', $route->driver_id)
            ->whereBetween('recorded_at', [$route->started_at, now()])->orderBy('recorded_at')
            ->get(['latitude', 'longitude'])->map(fn ($l) => [$l->latitude, $l->longitude])->all();

        return count($pts) > 1 ? Geo::pathKm($pts) : null;
    }

    /** @return list<array{pt:array{0:float,1:float},stop_id:?int}> */
    private function points(Route $route): array
    {
        $pts = [];
        if (Geo::valid($route->start_latitude, $route->start_longitude)) {
            $pts[] = ['pt' => [$route->start_latitude, $route->start_longitude], 'stop_id' => null];
        }
        foreach ($route->stops()->get() as $s) {
            if (Geo::valid($s->latitude, $s->longitude)) {
                $pts[] = ['pt' => [$s->latitude, $s->longitude], 'stop_id' => $s->id];
            }
        }
        if (Geo::valid($route->end_latitude, $route->end_longitude)) {
            $pts[] = ['pt' => [$route->end_latitude, $route->end_longitude], 'stop_id' => null];
        }

        return $pts;
    }

    private function assertEditable(Route $route): void
    {
        if (in_array($route->status, [RouteStatus::Completed, RouteStatus::Cancelled], true)) {
            throw new DomainException('This route is closed.', 'route_closed');
        }
    }
}

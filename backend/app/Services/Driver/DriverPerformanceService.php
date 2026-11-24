<?php

namespace App\Services\Driver;

use App\Enums\DeliveryStatus;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverShift;
use App\Models\VehicleLocation;
use App\Support\Geo;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/** All metrics computed from real records. Null where no data exists. */
class DriverPerformanceService
{
    public function metrics(Driver $driver, ?CarbonInterface $from = null, ?CarbonInterface $to = null): array
    {
        $base = Delivery::where('assigned_driver_id', $driver->id)
            ->when($from, fn ($q) => $q->where('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('created_at', '<=', $to));

        $byStatus = (clone $base)->select('status', DB::raw('count(*) as c'))->groupBy('status')->pluck('c', 'status');
        $total = (int) $byStatus->sum();
        $completed = (int) ($byStatus[DeliveryStatus::Delivered->value] ?? 0);
        $failed = (int) ($byStatus[DeliveryStatus::Failed->value] ?? 0) + (int) ($byStatus[DeliveryStatus::Returned->value] ?? 0);

        $done = (clone $base)->where('status', DeliveryStatus::Delivered)->get(['scheduled_delivery_at', 'actual_delivery_at', 'actual_pickup_at', 'customer_rating']);
        $withSchedule = $done->filter(fn ($d) => $d->scheduled_delivery_at && $d->actual_delivery_at);
        $onTime = $withSchedule->filter(fn ($d) => $d->actual_delivery_at->lte($d->scheduled_delivery_at))->count();
        $durations = $done->filter(fn ($d) => $d->actual_pickup_at && $d->actual_delivery_at)
            ->map(fn ($d) => $d->actual_pickup_at->diffInMinutes($d->actual_delivery_at));
        $ratings = $done->pluck('customer_rating')->filter();

        $shiftMinutes = (int) DriverShift::where('driver_id', $driver->id)
            ->when($from, fn ($q) => $q->where('started_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('started_at', '<=', $to))
            ->sum('duration_minutes');

        return [
            'total_deliveries' => $total,
            'completed_deliveries' => $completed,
            'failed_deliveries' => $failed,
            'on_time_deliveries' => $onTime,
            'on_time_rate' => $withSchedule->count() ? round($onTime / $withSchedule->count() * 100, 1) : null,
            'success_rate' => ($completed + $failed) ? round($completed / ($completed + $failed) * 100, 1) : null,
            'average_delivery_minutes' => $durations->count() ? round($durations->avg(), 1) : null,
            'distance_km' => $this->distance($driver, $from, $to),
            'average_rating' => $ratings->count() ? round($ratings->avg(), 2) : null,
            'ratings_count' => $ratings->count(),
            'working_hours' => round($shiftMinutes / 60, 1),
        ];
    }

    /** Distance from recorded GPS points (sum of consecutive segments, ignoring jumps > 5 km between pings). */
    public function distance(Driver $driver, ?CarbonInterface $from, ?CarbonInterface $to): ?float
    {
        $km = 0.0;
        $prev = null;
        $any = false;
        VehicleLocation::where('driver_id', $driver->id)
            ->when($from, fn ($q) => $q->where('recorded_at', '>=', $from))
            ->when($to, fn ($q) => $q->where('recorded_at', '<=', $to))
            ->orderBy('recorded_at')
            ->select(['latitude', 'longitude'])
            ->chunk(2000, function ($rows) use (&$km, &$prev, &$any) {
                foreach ($rows as $r) {
                    $any = true;
                    if ($prev) {
                        $d = Geo::distanceKm($prev[0], $prev[1], $r->latitude, $r->longitude);
                        if ($d < 5) {
                            $km += $d;
                        }
                    }
                    $prev = [$r->latitude, $r->longitude];
                }
            });

        return $any ? round($km, 1) : null;
    }
}

<?php

namespace App\Services\Reports;

use App\Enums\DeliveryStatus;
use App\Enums\DriverStatus;
use App\Enums\ExpenseStatus;
use App\Enums\InvoiceStatus;
use App\Enums\MaintenanceStatus;
use App\Enums\VehicleStatus;
use App\Models\Customer;
use App\Models\Delivery;
use App\Models\Driver;
use App\Models\DriverDocument;
use App\Models\Expense;
use App\Models\FuelRecord;
use App\Models\Invoice;
use App\Models\MaintenanceRecord;
use App\Models\Payment;
use App\Models\Vehicle;
use App\Models\VehicleDocument;
use App\Services\Driver\DriverPerformanceService;
use App\Services\Fuel\FuelService;
use App\Services\Tracking\LocationService;
use App\Support\DateRange;
use App\Support\Money;
use App\Support\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Every figure is computed from database records inside the current tenant
 * (BelongsToCompany scope). Where a metric has no underlying data it is null —
 * never estimated.
 */
class ReportService
{
    public function __construct(
        private readonly DriverPerformanceService $performance,
        private readonly FuelService $fuel,
    ) {}

    // ---------------------------------------------------------------- dashboard

    public function dashboard(DateRange $r): array
    {
        $vehicleStatus = Vehicle::select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $drivers = Driver::where('status', DriverStatus::Active);
        $inRange = Delivery::whereBetween('created_at', [$r->from, $r->to]);
        $byStatus = (clone $inRange)->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $todayStart = now($r->timezone)->startOfDay()->utc();

        $delivered = Delivery::where('status', DeliveryStatus::Delivered)->whereBetween('actual_delivery_at', [$r->from, $r->to]);
        $onTime = $this->onTime(clone $delivered);
        $failed = (int) Delivery::whereIn('status', ['failed', 'returned'])->whereBetween('updated_at', [$r->from, $r->to])->count();
        $deliveredCount = (clone $delivered)->count();

        return [
            'range' => $r->toArray(),
            'kpis' => [
                'total_vehicles' => (int) $vehicleStatus->sum(),
                'available_vehicles' => (int) ($vehicleStatus[VehicleStatus::Available->value] ?? 0),
                'vehicles_on_road' => (int) ($vehicleStatus[VehicleStatus::OnDelivery->value] ?? 0),
                'vehicles_in_maintenance' => (int) ($vehicleStatus[VehicleStatus::UnderMaintenance->value] ?? 0),
                'active_drivers' => (clone $drivers)->count(),
                'available_drivers' => (clone $drivers)->where('availability', 'available')->count(),
                'on_duty_drivers' => (clone $drivers)->where('availability', 'on_duty')->count(),
                'total_deliveries' => (int) $byStatus->sum(),
                'deliveries_today' => Delivery::where('created_at', '>=', $todayStart)->count(),
                'pending_deliveries' => Delivery::whereIn('status', ['pending', 'draft'])->count(),
                'active_deliveries' => Delivery::whereIn('status', DeliveryStatus::activeValues())->count(),
                'completed_deliveries' => $deliveredCount,
                'failed_deliveries' => $failed,
                'on_time_rate' => $onTime['rate'],
                'success_rate' => ($deliveredCount + $failed) ? round($deliveredCount / ($deliveredCount + $failed) * 100, 1) : null,
                'delivery_revenue' => Money::fromCents(Money::toCents((string) (clone $delivered)->sum('delivery_fee'))),
            ],
            'charts' => [
                'deliveries_over_time' => $this->deliveriesOverTime($r),
                'status_distribution' => $byStatus->map(fn ($c, $s) => ['status' => $s, 'count' => (int) $c])->values(),
                'fleet_utilization' => $this->utilizationSeries($r),
                'revenue_over_time' => $this->sumSeries($r, Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to]), 'actual_delivery_at', 'delivery_fee'),
                'fuel_expenses' => $this->sumSeries($r, FuelRecord::whereBetween('recorded_at', [$r->from, $r->to]), 'recorded_at', 'total_cost'),
                'maintenance_expenses' => $this->sumSeries($r, MaintenanceRecord::where('status', MaintenanceStatus::Completed)->whereDate('completed_date', '>=', $r->from->toDateString())->whereDate('completed_date', '<=', $r->to->toDateString()), 'completed_date', 'cost'),
                'driver_performance' => $this->topDrivers($r, 8),
                'average_delivery_minutes' => $this->avgDuration(clone $delivered),
            ],
            'widgets' => [
                'recent_deliveries' => Delivery::with(['driver:id,first_name,last_name', 'customer:id,name'])->latest()->limit(6)->get()
                    ->map(fn ($d) => ['id' => $d->id, 'reference' => $d->reference_number, 'status' => $d->status->value, 'recipient' => $d->recipient_name, 'city' => $d->delivery_city, 'driver' => $d->driver?->fullName(), 'created_at' => $d->created_at->toIso8601String()]),
                'active_drivers' => Driver::where('availability', 'on_duty')->with('activeVehicleAssignment.vehicle:id,registration_number,last_located_at')->limit(8)->get()
                    ->map(fn ($d) => ['id' => $d->id, 'name' => $d->fullName(), 'vehicle' => $d->activeVehicleAssignment?->vehicle?->registration_number, 'last_seen' => $d->activeVehicleAssignment?->vehicle?->last_located_at?->toIso8601String(), 'active_deliveries' => $d->deliveries()->whereIn('status', DeliveryStatus::activeValues())->count()]),
                'upcoming_maintenance' => MaintenanceRecord::with('vehicle:id,registration_number')->where('status', 'scheduled')->orderBy('scheduled_date')->limit(6)->get()
                    ->map(fn ($m) => ['id' => $m->id, 'title' => $m->title, 'vehicle' => $m->vehicle?->registration_number, 'vehicle_id' => $m->vehicle_id, 'date' => $m->scheduled_date?->toDateString(), 'overdue' => $m->scheduled_date?->isPast() ?? false]),
                'expiring_documents' => $this->expiringDocuments(30),
                'recent_expenses' => Expense::with('vehicle:id,registration_number')->latest()->limit(6)->get()
                    ->map(fn ($e) => ['id' => $e->id, 'description' => $e->description, 'category' => $e->category->value, 'amount' => $e->amount, 'status' => $e->status->value, 'date' => $e->expense_date->toDateString()]),
                'delivery_exceptions' => Delivery::where('status', 'failed')->latest('updated_at')->limit(6)->get()
                    ->map(fn ($d) => ['id' => $d->id, 'reference' => $d->reference_number, 'reason' => $d->failure_reason, 'at' => $d->updated_at->toIso8601String()])
                    ->merge(Delivery::whereIn('status', DeliveryStatus::activeValues())->whereNotNull('scheduled_delivery_at')->where('scheduled_delivery_at', '<', now())->limit(6)->get()
                        ->map(fn ($d) => ['id' => $d->id, 'reference' => $d->reference_number, 'reason' => 'Late — was due '.$d->scheduled_delivery_at->diffForHumans(), 'at' => $d->scheduled_delivery_at->toIso8601String()]))
                    ->take(8)->values(),
                'live_fleet' => Vehicle::whereNotNull('last_located_at')->get(['id', 'registration_number', 'status', 'last_latitude', 'last_longitude', 'last_located_at'])
                    ->map(fn ($v) => ['id' => $v->id, 'registration_number' => $v->registration_number, 'status' => $v->status->value, 'latitude' => $v->last_latitude, 'longitude' => $v->last_longitude, 'located_at' => $v->last_located_at->toIso8601String(), 'freshness' => LocationService::freshness($v->last_located_at)]),
            ],
        ];
    }

    // ---------------------------------------------------------------- deliveries

    public function deliveries(DateRange $r): array
    {
        $base = Delivery::whereBetween('created_at', [$r->from, $r->to]);
        $byStatus = (clone $base)->select('status', DB::raw('count(*) c'))->groupBy('status')->pluck('c', 'status');
        $delivered = (int) ($byStatus['delivered'] ?? 0);
        $failed = (int) ($byStatus['failed'] ?? 0) + (int) ($byStatus['returned'] ?? 0);
        $onTime = $this->onTime(Delivery::where('status', 'delivered')->whereBetween('created_at', [$r->from, $r->to]));

        return [
            'range' => $r->toArray(),
            'summary' => [
                'total' => (int) $byStatus->sum(),
                'delivered' => $delivered,
                'failed' => $failed,
                'cancelled' => (int) ($byStatus['cancelled'] ?? 0),
                'success_rate' => ($delivered + $failed) ? round($delivered / ($delivered + $failed) * 100, 1) : null,
                'on_time_rate' => $onTime['rate'],
                'on_time_measured' => $onTime['measured'],
                'average_delivery_minutes' => $this->avgDuration(Delivery::where('status', 'delivered')->whereBetween('created_at', [$r->from, $r->to])),
            ],
            'by_date' => $this->deliveriesOverTime($r),
            'by_status' => $byStatus->map(fn ($c, $s) => ['status' => $s, 'count' => (int) $c])->values(),
            'failure_reasons' => (clone $base)->whereIn('status', ['failed', 'returned'])->whereNotNull('failure_reason')
                ->select('failure_reason', DB::raw('count(*) c'))->groupBy('failure_reason')->orderByDesc('c')->limit(10)->get()
                ->map(fn ($x) => ['reason' => $x->failure_reason, 'count' => (int) $x->c]),
            'by_city' => (clone $base)->whereNotNull('delivery_city')->select('delivery_city', DB::raw('count(*) c'))
                ->groupBy('delivery_city')->orderByDesc('c')->limit(12)->get()->map(fn ($x) => ['city' => $x->delivery_city, 'count' => (int) $x->c]),
            'rows' => (clone $base)->with(['customer:id,name', 'driver:id,first_name,last_name', 'vehicle:id,registration_number'])->latest()->limit(2000)->get()
                ->map(fn (Delivery $d) => [
                    'reference' => $d->reference_number, 'created_at' => $d->created_at->toDateTimeString(), 'status' => $d->status->value,
                    'priority' => $d->priority->value, 'customer' => $d->customer?->name, 'recipient' => $d->recipient_name,
                    'city' => $d->delivery_city, 'driver' => $d->driver?->fullName(), 'vehicle' => $d->vehicle?->registration_number,
                    'scheduled_delivery_at' => $d->scheduled_delivery_at?->toDateTimeString(), 'delivered_at' => $d->actual_delivery_at?->toDateTimeString(),
                    'on_time' => $d->isOnTime(), 'fee' => $d->delivery_fee, 'cod' => $d->cod_amount, 'failure_reason' => $d->failure_reason,
                ]),
        ];
    }

    // ---------------------------------------------------------------- fleet

    public function fleet(DateRange $r): array
    {
        $vehicles = Vehicle::with('type')->get();
        $days = max(1, (int) ceil($r->from->diffInDays($r->to)));
        $usage = Delivery::whereNotNull('assigned_vehicle_id')->whereNotNull('actual_pickup_at')
            ->whereBetween('actual_pickup_at', [$r->from, $r->to])->get(['assigned_vehicle_id', 'actual_pickup_at', 'actual_delivery_at', 'status', 'distance_km'])
            ->groupBy('assigned_vehicle_id');
        $fuel = FuelRecord::whereBetween('recorded_at', [$r->from, $r->to])->get()->groupBy('vehicle_id');
        $costs = Expense::where('status', ExpenseStatus::Approved)->whereNotNull('vehicle_id')->whereDate('expense_date', '>=', $r->from->toDateString())->whereDate('expense_date', '<=', $r->to->toDateString())
            ->select('vehicle_id', 'category', DB::raw('sum(amount) s'))->groupBy('vehicle_id', 'category')->get()->groupBy('vehicle_id');
        $pendingCosts = Expense::where('status', ExpenseStatus::Pending)->whereNotNull('vehicle_id')->whereDate('expense_date', '>=', $r->from->toDateString())->whereDate('expense_date', '<=', $r->to->toDateString())
            ->select('vehicle_id', DB::raw('sum(amount) s'))->groupBy('vehicle_id')->pluck('s', 'vehicle_id');
        $maint = MaintenanceRecord::where('status', 'completed')->whereDate('completed_date', '>=', $r->from->toDateString())->whereDate('completed_date', '<=', $r->to->toDateString())
            ->select('vehicle_id', DB::raw('count(*) c'))->groupBy('vehicle_id')->pluck('c', 'vehicle_id');

        $rows = $vehicles->map(function (Vehicle $v) use ($usage, $fuel, $costs, $pendingCosts, $maint, $days) {
            $trips = $usage->get($v->id, collect());
            $activeDays = $trips->map(fn ($d) => $d->actual_pickup_at->toDateString())->unique()->count();
            $eff = $this->fuel->efficiency($fuel->get($v->id, collect()));
            $cost = $costs->get($v->id, collect());
            $totalCost = Money::add(...$cost->pluck('s')->map(fn ($x) => (string) $x)->all() ?: ['0']);

            return [
                'id' => $v->id,
                'registration_number' => $v->registration_number,
                'vehicle' => "{$v->make} {$v->model}",
                'type' => $v->type?->name,
                'status' => $v->status->value,
                'odometer' => $v->odometer,
                'deliveries' => $trips->count(),
                'delivered' => $trips->where('status', DeliveryStatus::Delivered)->count(),
                'active_days' => $activeDays,
                'utilization_rate' => round($activeDays / $days * 100, 1),
                'fuel_litres' => round((float) $fuel->get($v->id, collect())->sum('quantity'), 2),
                'fuel_cost' => Money::add(...$fuel->get($v->id, collect())->pluck('total_cost')->map(fn ($x) => (string) $x)->all() ?: ['0']),
                'l_per_100km' => $eff['l_per_100km'],
                'cost_per_km' => $eff['distance_km'] ? Money::fromCents((int) round(Money::toCents($totalCost) / $eff['distance_km'])) : null,
                'approved_costs' => $totalCost,
                'pending_costs' => Money::fromCents(Money::toCents((string) ($pendingCosts[$v->id] ?? 0))),
                'cost_breakdown' => $cost->mapWithKeys(fn ($c) => [$c->category instanceof \BackedEnum ? $c->category->value : $c->category => Money::fromCents(Money::toCents((string) $c->s))]),
                'maintenance_events' => (int) ($maint[$v->id] ?? 0),
                'next_service_date' => $v->next_service_date?->toDateString(),
            ];
        })->values();

        return [
            'range' => $r->toArray(),
            'summary' => [
                'vehicles' => $vehicles->count(),
                'by_status' => $vehicles->countBy(fn ($v) => $v->status->value),
                'average_utilization' => $rows->count() ? round($rows->avg('utilization_rate'), 1) : null,
                'fuel_litres' => round($rows->sum('fuel_litres'), 2),
                'fuel_cost' => Money::add(...$rows->pluck('fuel_cost')->all() ?: ['0']),
                'operating_costs' => Money::add(...$rows->pluck('approved_costs')->all() ?: ['0']),
                'pending_costs' => Money::add(...$rows->pluck('pending_costs')->all() ?: ['0']),
            ],
            'utilization' => $this->utilizationSeries($r),
            'rows' => $rows,
        ];
    }

    // ---------------------------------------------------------------- drivers

    public function drivers(DateRange $r): array
    {
        $rows = Driver::orderBy('first_name')->get()->map(fn (Driver $d) => [
            'id' => $d->id, 'name' => $d->fullName(), 'status' => $d->status->value, 'availability' => $d->availability->value,
        ] + $this->performance->metrics($d, $r->from, $r->to))->values();

        return [
            'range' => $r->toArray(),
            'summary' => [
                'drivers' => $rows->count(),
                'completed' => $rows->sum('completed_deliveries'),
                'failed' => $rows->sum('failed_deliveries'),
                'working_hours' => round($rows->sum('working_hours'), 1),
                'distance_km' => $rows->pluck('distance_km')->filter()->isNotEmpty() ? round($rows->sum('distance_km'), 1) : null,
            ],
            'rows' => $rows,
        ];
    }

    // ---------------------------------------------------------------- financial

    public function financial(DateRange $r): array
    {
        $dFrom = $r->from->toDateString();
        $dTo = $r->to->toDateString();
        $delivered = Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to]);
        $revenue = Money::fromCents(Money::toCents((string) (clone $delivered)->sum('delivery_fee')));

        $approved = Expense::where('status', ExpenseStatus::Approved)->whereDate('expense_date', '>=', $dFrom)->whereDate('expense_date', '<=', $dTo);
        $pending = Expense::where('status', ExpenseStatus::Pending)->whereDate('expense_date', '>=', $dFrom)->whereDate('expense_date', '<=', $dTo);
        $approvedTotal = Money::fromCents(Money::toCents((string) (clone $approved)->sum('amount')));
        $pendingTotal = Money::fromCents(Money::toCents((string) (clone $pending)->sum('amount')));
        $pendingCount = (clone $pending)->count();

        $invoiced = Invoice::whereNotIn('status', [InvoiceStatus::Void->value, InvoiceStatus::Draft->value])->whereBetween('issued_at', [$r->from, $r->to]);
        $collected = Payment::whereBetween('paid_at', [$r->from, $r->to])->sum('amount');
        $outstanding = Invoice::whereIn('status', ['issued', 'partially_paid'])->get()->reduce(fn ($c, Invoice $i) => $c + Money::toCents($i->balance()), 0);

        $codDue = (clone $delivered)->where('cod_amount', '>', 0);
        $codExpected = Money::fromCents(Money::toCents((string) (clone $codDue)->sum('cod_amount')));
        $codCollected = Money::fromCents(Money::toCents((string) (clone $codDue)->sum('cod_collected_amount')));

        $net = Money::sub($revenue, $approvedTotal);
        $warnings = [];
        if ($pendingCount > 0) {
            $warnings[] = "{$pendingCount} expense(s) totalling {$pendingTotal} are pending approval and are NOT included in the operating margin.";
        }
        $unpriced = (clone $delivered)->where('delivery_fee', 0)->count();
        if ($unpriced > 0) {
            $warnings[] = "{$unpriced} delivered order(s) have no delivery fee recorded; revenue may be understated.";
        }

        return [
            'range' => $r->toArray(),
            'currency' => app(TenantContext::class)->company()?->currency,
            'summary' => [
                'delivery_revenue' => $revenue,
                'invoiced' => Money::fromCents(Money::toCents((string) (clone $invoiced)->sum('total_amount'))),
                'collected' => Money::fromCents(Money::toCents((string) $collected)),
                'outstanding' => Money::fromCents($outstanding),
                'approved_expenses' => $approvedTotal,
                'pending_expenses' => $pendingTotal,
                'net_operating_margin' => $net,
                'margin_rate' => Money::toCents($revenue) > 0 ? round(Money::toCents($net) / Money::toCents($revenue) * 100, 1) : null,
                'cod_expected' => $codExpected,
                'cod_collected' => $codCollected,
                'cod_variance' => Money::sub($codCollected, $codExpected),
                'complete' => $warnings === [],
            ],
            'warnings' => $warnings,
            'expenses_by_category' => (clone $approved)->select('category', DB::raw('sum(amount) s'))->groupBy('category')->get()
                ->map(fn ($x) => ['category' => $x->category instanceof \BackedEnum ? $x->category->value : $x->category, 'amount' => Money::fromCents(Money::toCents((string) $x->s))]),
            'revenue_over_time' => $this->sumSeries($r, Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to]), 'actual_delivery_at', 'delivery_fee'),
            'expenses_over_time' => $this->sumSeries($r, Expense::where('status', 'approved')->whereDate('expense_date', '>=', $dFrom)->whereDate('expense_date', '<=', $dTo), 'expense_date', 'amount'),
            'revenue_by_customer' => Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to])->whereNotNull('customer_id')
                ->select('customer_id', DB::raw('count(*) c'), DB::raw('sum(delivery_fee) s'))->groupBy('customer_id')->orderByDesc('s')->limit(15)->get()
                ->map(fn ($x) => ['customer_id' => $x->customer_id, 'customer' => Customer::withTrashed()->find($x->customer_id)?->name, 'deliveries' => (int) $x->c, 'revenue' => Money::fromCents(Money::toCents((string) $x->s))]),
            'customer_balances' => Invoice::with('customer:id,name')->whereIn('status', ['issued', 'partially_paid'])->get()->groupBy('customer_id')
                ->map(fn ($inv) => ['customer' => $inv->first()->customer?->name, 'invoices' => $inv->count(), 'balance' => Money::fromCents($inv->sum(fn ($i) => Money::toCents($i->balance())))])->values(),
            'profitability_by_delivery' => $this->deliveryProfitability($r),
            'cost_per_vehicle' => $this->fleet($r)['rows']->map(fn ($x) => ['vehicle' => $x['registration_number'], 'approved_costs' => $x['approved_costs'], 'cost_per_km' => $x['cost_per_km']])->values(),
        ];
    }

    /** Revenue minus directly-attributed approved expenses (expenses linked to the delivery). */
    private function deliveryProfitability(DateRange $r): array
    {
        $deliveries = Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to])->latest('actual_delivery_at')->limit(50)->get(['id', 'reference_number', 'delivery_fee', 'distance_km']);
        $direct = Expense::where('status', 'approved')->whereIn('delivery_id', $deliveries->pluck('id'))->select('delivery_id', DB::raw('sum(amount) s'))->groupBy('delivery_id')->pluck('s', 'delivery_id');
        $pending = Expense::where('status', 'pending')->whereIn('delivery_id', $deliveries->pluck('id'))->pluck('delivery_id')->unique();

        return $deliveries->map(fn ($d) => [
            'id' => $d->id,
            'reference' => $d->reference_number,
            'revenue' => $d->delivery_fee,
            'direct_costs' => Money::fromCents(Money::toCents((string) ($direct[$d->id] ?? 0))),
            'margin' => Money::sub($d->delivery_fee, (string) ($direct[$d->id] ?? 0)),
            'incomplete' => $pending->contains($d->id),
        ])->all();
    }

    // ---------------------------------------------------------------- helpers

    private function onTime($query): array
    {
        $rows = $query->whereNotNull('scheduled_delivery_at')->whereNotNull('actual_delivery_at')->get(['scheduled_delivery_at', 'actual_delivery_at']);
        $ok = $rows->filter(fn ($d) => $d->actual_delivery_at->lte($d->scheduled_delivery_at))->count();

        return ['rate' => $rows->count() ? round($ok / $rows->count() * 100, 1) : null, 'measured' => $rows->count()];
    }

    private function avgDuration($query): ?float
    {
        $d = $query->whereNotNull('actual_pickup_at')->whereNotNull('actual_delivery_at')->get(['actual_pickup_at', 'actual_delivery_at'])
            ->map(fn ($x) => $x->actual_pickup_at->diffInMinutes($x->actual_delivery_at));

        return $d->count() ? round($d->avg(), 1) : null;
    }

    private function deliveriesOverTime(DateRange $r): array
    {
        $series = array_fill_keys($r->keys(), ['created' => 0, 'delivered' => 0, 'failed' => 0]);
        foreach (Delivery::whereBetween('created_at', [$r->from, $r->to])->select('created_at')->cursor() as $d) {
            $k = $r->keyFor($d->created_at);
            if (isset($series[$k])) {
                $series[$k]['created']++;
            }
        }
        foreach (Delivery::where('status', 'delivered')->whereBetween('actual_delivery_at', [$r->from, $r->to])->select('actual_delivery_at')->cursor() as $d) {
            $k = $r->keyFor($d->actual_delivery_at);
            if (isset($series[$k])) {
                $series[$k]['delivered']++;
            }
        }
        foreach (Delivery::whereIn('status', ['failed', 'returned'])->whereBetween('updated_at', [$r->from, $r->to])->select('updated_at')->cursor() as $d) {
            $k = $r->keyFor($d->updated_at);
            if (isset($series[$k])) {
                $series[$k]['failed']++;
            }
        }

        return collect($series)->map(fn ($v, $k) => ['date' => $k] + $v)->values()->all();
    }

    private function sumSeries(DateRange $r, $query, string $dateCol, string $amountCol): array
    {
        $series = array_fill_keys($r->keys(), 0);
        foreach ($query->select([$dateCol, $amountCol])->cursor() as $row) {
            $k = $r->keyFor($row->{$dateCol});
            if (isset($series[$k])) {
                $series[$k] += Money::toCents((string) $row->{$amountCol});
            }
        }

        return collect($series)->map(fn ($c, $k) => ['date' => $k, 'amount' => Money::fromCents($c)])->values()->all();
    }

    /** Share of the fleet that did at least one delivery each day. */
    private function utilizationSeries(DateRange $r): array
    {
        $total = Vehicle::whereNotIn('status', ['inactive'])->count();
        $series = array_fill_keys($r->keys(), []);
        foreach (Delivery::whereNotNull('assigned_vehicle_id')->whereNotNull('actual_pickup_at')->whereBetween('actual_pickup_at', [$r->from, $r->to])->select(['assigned_vehicle_id', 'actual_pickup_at'])->cursor() as $d) {
            $k = $r->keyFor($d->actual_pickup_at);
            if (isset($series[$k])) {
                $series[$k][$d->assigned_vehicle_id] = true;
            }
        }

        return collect($series)->map(fn ($v, $k) => ['date' => $k, 'vehicles_used' => count($v), 'rate' => $total ? round(count($v) / $total * 100, 1) : null])->values()->all();
    }

    private function topDrivers(DateRange $r, int $limit): array
    {
        return Delivery::whereNotNull('assigned_driver_id')->whereIn('status', ['delivered', 'failed', 'returned'])->whereBetween('updated_at', [$r->from, $r->to])
            ->select('assigned_driver_id', DB::raw("sum(case when status = 'delivered' then 1 else 0 end) delivered"), DB::raw("sum(case when status <> 'delivered' then 1 else 0 end) failed"))
            ->groupBy('assigned_driver_id')->orderByDesc('delivered')->limit($limit)->get()
            ->map(fn ($x) => ['driver_id' => $x->assigned_driver_id, 'name' => Driver::withTrashed()->find($x->assigned_driver_id)?->fullName(), 'delivered' => (int) $x->delivered, 'failed' => (int) $x->failed])->all();
    }

    public function expiringDocuments(int $days): array
    {
        $until = now()->addDays($days)->toDateString();
        $out = collect();
        foreach (Vehicle::where(fn ($q) => $q->where('insurance_expiry_date', '<=', $until)->orWhere('registration_expiry_date', '<=', $until))->get() as $v) {
            foreach (['insurance_expiry_date' => 'Insurance', 'registration_expiry_date' => 'Registration'] as $f => $label) {
                if ($v->{$f} && $v->{$f}->toDateString() <= $until) {
                    $out->push(['type' => $label, 'subject' => $v->registration_number, 'link' => "/app/vehicles/{$v->id}", 'expires' => $v->{$f}->toDateString(), 'expired' => $v->{$f}->isPast()]);
                }
            }
        }
        foreach (VehicleDocument::with('vehicle:id,registration_number')->where('expiry_date', '<=', $until)->get() as $d) {
            $out->push(['type' => ucfirst(str_replace('_', ' ', $d->document_type)), 'subject' => $d->vehicle?->registration_number, 'link' => "/app/vehicles/{$d->vehicle_id}", 'expires' => $d->expiry_date->toDateString(), 'expired' => $d->expiry_date->isPast()]);
        }
        foreach (Driver::where('status', 'active')->where('license_expiry_date', '<=', $until)->get() as $d) {
            $out->push(['type' => 'Driver licence', 'subject' => $d->fullName(), 'link' => "/app/drivers/{$d->id}", 'expires' => $d->license_expiry_date->toDateString(), 'expired' => $d->license_expiry_date->isPast()]);
        }
        foreach (DriverDocument::with('driver:id,first_name,last_name')->where('expiry_date', '<=', $until)->get() as $d) {
            $out->push(['type' => ucfirst(str_replace('_', ' ', $d->document_type)), 'subject' => $d->driver?->fullName(), 'link' => "/app/drivers/{$d->driver_id}", 'expires' => $d->expiry_date->toDateString(), 'expired' => $d->expiry_date->isPast()]);
        }

        return $out->unique(fn ($x) => $x['type'].$x['subject'].$x['expires'])->sortBy('expires')->take(10)->values()->all();
    }
}

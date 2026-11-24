<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Controller;
use App\Services\Reports\Exporter;
use App\Services\Reports\ReportService;
use App\Support\ApiResponse;
use App\Support\DateRange;
use App\Support\Tenancy\TenantContext;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function __construct(private readonly ReportService $reports, private readonly TenantContext $tenant) {}

    private function range(Request $request, string $default = 'last_30_days'): DateRange
    {
        return DateRange::fromRequest($request, $this->tenant->companyOrFail()->timezone, $default);
    }

    public function dashboard(Request $request): JsonResponse
    {
        $data = $this->reports->dashboard($this->range($request));
        $user = $request->user();
        // Hide financial figures from roles without financial access.
        if (! $user->can('reports.financial') && ! $user->can('invoices.view')) {
            $data['kpis']['delivery_revenue'] = null;
            $data['charts']['revenue_over_time'] = [];
            $data['charts']['fuel_expenses'] = $user->can('fuel.view') ? $data['charts']['fuel_expenses'] : [];
            $data['charts']['maintenance_expenses'] = $user->can('maintenance.view') ? $data['charts']['maintenance_expenses'] : [];
            $data['widgets']['recent_expenses'] = $user->can('expenses.view') ? $data['widgets']['recent_expenses'] : [];
        }
        $data['kpis'] = ['total_vehicles' => $data['kpis']['total_vehicles']] + $data['kpis'];

        return ApiResponse::ok($data);
    }

    public function show(Request $request, string $type, Exporter $exporter)
    {
        $perm = ['deliveries' => 'reports.deliveries', 'fleet' => 'reports.fleet', 'drivers' => 'reports.drivers', 'financial' => 'reports.financial'][$type] ?? null;
        abort_unless($perm, 404);
        if (! $request->user()->can($perm)) {
            throw new AuthorizationException;
        }
        $data = $this->reports->{$type}($this->range($request));

        if ($format = $request->query('export')) {
            $rows = match ($type) {
                'financial' => collect($data['revenue_by_customer'])->all(),
                default => collect($data['rows'])->map(fn ($r) => collect($r)->except(['cost_breakdown'])->all())->all(),
            };

            return $exporter->download($format, "{$type}-report-".now()->format('Ymd'), $rows);
        }

        return ApiResponse::ok($data);
    }
}

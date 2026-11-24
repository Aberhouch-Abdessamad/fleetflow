<?php

namespace App\Services\Fuel;

use App\Enums\ExpenseCategory;
use App\Exceptions\DomainException;
use App\Models\FuelRecord;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use App\Services\Expense\ExpenseService;
use App\Support\Money;
use App\Support\Uploads;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FuelService
{
    public function __construct(private readonly ExpenseService $expenses, private readonly ActivityLogger $logger) {}

    public function create(array $data, User $by, ?UploadedFile $receipt = null): FuelRecord
    {
        return DB::transaction(function () use ($data, $by, $receipt) {
            $vehicle = Vehicle::whereKey($data['vehicle_id'])->lockForUpdate()->firstOrFail();
            $prev = FuelRecord::where('vehicle_id', $vehicle->id)->where('recorded_at', '<=', $data['recorded_at'])->max('odometer');
            if ($prev !== null && (int) $data['odometer'] < (int) $prev) {
                throw new DomainException("Odometer must be at least {$prev} km (last fuel record).", 'odometer_regression');
            }

            $record = new FuelRecord(fillable(FuelRecord::class, $data));
            $record->unit_price = number_format((float) $data['unit_price'], 3, '.', '');
            // Total is computed by the backend from quantity × unit price.
            $record->total_cost = Money::mul($record->unit_price, $data['quantity']);
            $record->created_by = $by->id;
            if ($receipt) {
                $record->receipt_path = Uploads::store($receipt, $vehicle->company_id, 'fuel');
            }
            $record->save();

            if ((int) $data['odometer'] > $vehicle->odometer) {
                $vehicle->forceFill(['odometer' => (int) $data['odometer']])->save();
            }

            $this->expenses->createFromSource('fuel_record', $record->id, [
                'vehicle_id' => $vehicle->id,
                'driver_id' => $record->driver_id,
                'category' => ExpenseCategory::Fuel->value,
                'description' => sprintf('Fuel %s L at %s', $record->quantity, $record->station_name ?: 'station'),
                'amount' => $record->total_cost,
                'expense_date' => $record->recorded_at->toDateString(),
            ], $by);
            $this->logger->log('fuel.recorded', $record, ['total' => $record->total_cost]);

            return $record->load('vehicle', 'driver');
        });
    }

    /**
     * Consumption from full-to-full fills: litres added since the previous full
     * fill ÷ km driven. Partial fills are accumulated into the next full fill.
     */
    public function efficiency(Collection $records): array
    {
        $sorted = $records->sortBy([['recorded_at', 'asc'], ['odometer', 'asc']])->values();
        $segments = [];
        $lastFullOdo = null;
        $litres = 0.0;
        $cost = 0;
        foreach ($sorted as $r) {
            if ($lastFullOdo !== null) {
                $litres += (float) $r->quantity;
                $cost += Money::toCents($r->total_cost);
            }
            if ($r->full_tank) {
                if ($lastFullOdo !== null && $r->odometer > $lastFullOdo) {
                    $km = $r->odometer - $lastFullOdo;
                    $segments[] = ['km' => $km, 'litres' => $litres, 'cost_cents' => $cost];
                }
                $lastFullOdo = $r->odometer;
                $litres = 0.0;
                $cost = 0;
            }
        }
        $km = array_sum(array_column($segments, 'km'));
        $l = array_sum(array_column($segments, 'litres'));
        $c = array_sum(array_column($segments, 'cost_cents'));

        return [
            'distance_km' => $km ?: null,
            'litres' => $l ? round($l, 2) : null,
            'l_per_100km' => $km > 0 ? round($l / $km * 100, 2) : null,
            'km_per_l' => $l > 0 ? round($km / $l, 2) : null,
            'cost_per_km' => $km > 0 ? Money::fromCents((int) round($c / $km)) : null,
            'segments' => count($segments),
        ];
    }
}

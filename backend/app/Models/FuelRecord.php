<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FuelRecord extends Model
{
    use BelongsToCompany;

    protected $fillable = ['vehicle_id', 'driver_id', 'fuel_type', 'quantity', 'unit_price', 'odometer', 'full_tank', 'station_name', 'recorded_at'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_price' => 'decimal:3', 'total_cost' => 'decimal:2', 'full_tank' => 'boolean', 'recorded_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }
}

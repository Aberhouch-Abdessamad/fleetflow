<?php

namespace App\Models;

use App\Enums\FuelType;
use App\Enums\VehicleStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Vehicle extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'vehicle_type_id', 'registration_number', 'make', 'model', 'year', 'color', 'vin', 'fuel_type',
        'capacity', 'capacity_unit', 'odometer', 'insurance_provider', 'insurance_policy_number',
        'insurance_expiry_date', 'registration_expiry_date', 'last_service_date', 'next_service_date',
        'next_service_odometer', 'notes',
    ];

    protected $attributes = ['status' => 'available', 'capacity_unit' => 'kg', 'odometer' => 0];

    protected function casts(): array
    {
        return [
            'status' => VehicleStatus::class,
            'fuel_type' => FuelType::class,
            'capacity' => 'decimal:2',
            'insurance_expiry_date' => 'date',
            'registration_expiry_date' => 'date',
            'last_service_date' => 'date',
            'next_service_date' => 'date',
            'last_located_at' => 'datetime',
            'last_latitude' => 'float',
            'last_longitude' => 'float',
            'last_speed' => 'float',
            'last_heading' => 'float',
        ];
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(VehicleDocument::class);
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function activeAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class)->whereNull('unassigned_at');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_vehicle_id');
    }

    public function maintenanceRecords(): HasMany
    {
        return $this->hasMany(MaintenanceRecord::class);
    }

    public function fuelRecords(): HasMany
    {
        return $this->hasMany(FuelRecord::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(VehicleLocation::class);
    }

    public function lastDriver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'last_driver_id');
    }

    public function label(): string
    {
        return "{$this->registration_number} · {$this->make} {$this->model}";
    }
}

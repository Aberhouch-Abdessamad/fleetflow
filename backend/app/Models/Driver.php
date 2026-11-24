<?php

namespace App\Models;

use App\Enums\DriverAvailability;
use App\Enums\DriverStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Driver extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    protected $fillable = [
        'first_name', 'last_name', 'email', 'phone', 'license_number', 'license_type', 'license_expiry_date',
        'employment_date', 'emergency_contact', 'emergency_phone', 'national_id', 'address', 'date_of_birth', 'notes',
    ];

    protected $attributes = ['status' => 'active', 'availability' => 'available'];

    protected function casts(): array
    {
        return [
            'status' => DriverStatus::class,
            'availability' => DriverAvailability::class,
            'license_expiry_date' => 'date',
            'employment_date' => 'date',
            'date_of_birth' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(DriverDocument::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(DriverAvailabilityRecord::class);
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(DriverShift::class);
    }

    public function openShift(): HasOne
    {
        return $this->hasOne(DriverShift::class)->whereNull('ended_at');
    }

    public function vehicleAssignments(): HasMany
    {
        return $this->hasMany(VehicleAssignment::class);
    }

    public function activeVehicleAssignment(): HasOne
    {
        return $this->hasOne(VehicleAssignment::class)->whereNull('unassigned_at');
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(Delivery::class, 'assigned_driver_id');
    }

    public function fullName(): string
    {
        return trim("{$this->first_name} {$this->last_name}");
    }

    public function licenseValidOn(\DateTimeInterface $date): bool
    {
        return $this->license_expiry_date !== null && $this->license_expiry_date->endOfDay()->gte($date);
    }

    /**
     * License hierarchy: holders of a "higher" category may drive lower ones.
     * A (motorcycle) is separate; B ⊂ C ⊂ CE; D separate.
     */
    public function licenseCovers(?string $required): bool
    {
        if ($required === null || $required === '') {
            return true;
        }
        $have = strtoupper($this->license_type);
        $need = strtoupper($required);
        $covers = [
            'A' => ['A'],
            'B' => ['B'],
            'C' => ['B', 'C'],
            'CE' => ['B', 'C', 'CE'],
            'D' => ['B', 'D'],
        ];

        return in_array($need, $covers[$have] ?? [$have], true);
    }
}

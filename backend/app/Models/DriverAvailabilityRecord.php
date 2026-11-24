<?php

namespace App\Models;

use App\Enums\DriverAvailability;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class DriverAvailabilityRecord extends Model
{
    use BelongsToCompany;

    protected $table = 'driver_availabilities';

    protected $fillable = ['driver_id', 'status', 'starts_at', 'ends_at', 'reason', 'recorded_by'];

    protected function casts(): array
    {
        return ['status' => DriverAvailability::class, 'starts_at' => 'datetime', 'ends_at' => 'datetime'];
    }
}

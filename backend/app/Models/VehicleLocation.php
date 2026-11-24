<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

class VehicleLocation extends Model
{
    use BelongsToCompany;

    public $timestamps = false;

    protected $fillable = ['vehicle_id', 'driver_id', 'delivery_id', 'latitude', 'longitude', 'speed', 'heading', 'accuracy', 'recorded_at', 'received_at'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'speed' => 'float', 'heading' => 'float', 'accuracy' => 'float', 'recorded_at' => 'datetime', 'received_at' => 'datetime'];
    }
}

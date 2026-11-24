<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Geofence extends Model
{
    use BelongsToCompany;

    protected $fillable = ['name', 'location_type', 'latitude', 'longitude', 'radius', 'status'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'radius' => 'integer'];
    }

    public function events(): HasMany
    {
        return $this->hasMany(GeofenceEvent::class);
    }
}

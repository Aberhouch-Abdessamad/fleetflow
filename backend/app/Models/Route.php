<?php

namespace App\Models;

use App\Enums\RouteStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Route extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'name', 'driver_id', 'vehicle_id', 'route_date', 'start_location', 'start_latitude', 'start_longitude',
        'end_location', 'end_latitude', 'end_longitude',
    ];

    protected $attributes = ['status' => 'planned'];

    protected function casts(): array
    {
        return [
            'status' => RouteStatus::class,
            'route_date' => 'date',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'geometry' => 'array',
            'planned_distance' => 'decimal:2',
            'actual_distance' => 'decimal:2',
            'start_latitude' => 'float', 'start_longitude' => 'float', 'end_latitude' => 'float', 'end_longitude' => 'float',
        ];
    }

    public function stops(): HasMany
    {
        return $this->hasMany(RouteStop::class)->orderBy('stop_order');
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function optimizationLogs(): HasMany
    {
        return $this->hasMany(RouteOptimizationLog::class)->latest('optimized_at');
    }
}

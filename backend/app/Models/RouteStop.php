<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RouteStop extends Model
{
    protected $fillable = ['delivery_id', 'stop_order', 'stop_type', 'address', 'latitude', 'longitude', 'priority', 'estimated_arrival', 'actual_arrival', 'completed_at', 'status', 'notes'];

    protected function casts(): array
    {
        return ['latitude' => 'float', 'longitude' => 'float', 'estimated_arrival' => 'datetime', 'actual_arrival' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }
}

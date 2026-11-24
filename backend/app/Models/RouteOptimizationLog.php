<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RouteOptimizationLog extends Model
{
    public $timestamps = false;

    protected $fillable = ['route_id', 'provider', 'original_sequence', 'optimized_sequence', 'distance_before', 'distance_after', 'duration_before', 'duration_after', 'requested_by', 'optimized_at'];

    protected function casts(): array
    {
        return ['original_sequence' => 'array', 'optimized_sequence' => 'array', 'optimized_at' => 'datetime'];
    }
}

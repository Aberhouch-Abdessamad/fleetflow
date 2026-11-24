<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only. */
class DeliveryStatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['delivery_id', 'previous_status', 'new_status', 'changed_by', 'source', 'notes', 'latitude', 'longitude', 'changed_at'];

    protected function casts(): array
    {
        return ['changed_at' => 'datetime', 'latitude' => 'float', 'longitude' => 'float'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}

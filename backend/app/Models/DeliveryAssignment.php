<?php

namespace App\Models;

use App\Enums\AssignmentStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeliveryAssignment extends Model
{
    use BelongsToCompany;

    protected $fillable = ['delivery_id', 'driver_id', 'vehicle_id', 'assigned_by', 'status', 'responded_at', 'response_reason'];

    protected function casts(): array
    {
        return ['status' => AssignmentStatus::class, 'responded_at' => 'datetime'];
    }

    public function delivery(): BelongsTo
    {
        return $this->belongsTo(Delivery::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class)->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

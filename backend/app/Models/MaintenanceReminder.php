<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceReminder extends Model
{
    use BelongsToCompany;

    protected $fillable = ['vehicle_id', 'reminder_type', 'subject_type', 'subject_id', 'due_date', 'due_odometer', 'status', 'notified_at'];

    protected function casts(): array
    {
        return ['due_date' => 'date', 'notified_at' => 'datetime'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }
}

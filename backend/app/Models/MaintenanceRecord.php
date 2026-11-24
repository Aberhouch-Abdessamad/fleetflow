<?php

namespace App\Models;

use App\Enums\MaintenanceStatus;
use App\Enums\MaintenanceType;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MaintenanceRecord extends Model
{
    use BelongsToCompany;

    protected $fillable = [
        'vehicle_id', 'maintenance_type', 'title', 'description', 'service_provider', 'scheduled_date', 'completed_date',
        'odometer', 'cost', 'next_service_date', 'next_service_odometer',
    ];

    protected $attributes = ['status' => 'scheduled'];

    protected function casts(): array
    {
        return [
            'maintenance_type' => MaintenanceType::class,
            'status' => MaintenanceStatus::class,
            'scheduled_date' => 'date',
            'completed_date' => 'date',
            'next_service_date' => 'date',
            'cost' => 'decimal:2',
        ];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class)->withTrashed();
    }
}

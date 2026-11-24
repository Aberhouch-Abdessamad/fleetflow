<?php

namespace App\Models;

use App\Enums\DeliveryPriority;
use App\Enums\DeliveryStatus;
use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Delivery extends Model
{
    use BelongsToCompany, HasFactory, SoftDeletes;

    /** status, assignment, financial-computed fields, tokens and company are set only by services. */
    protected $fillable = [
        'customer_id', 'pickup_address', 'pickup_latitude', 'pickup_longitude', 'pickup_contact_name', 'pickup_contact_phone',
        'delivery_address', 'delivery_latitude', 'delivery_longitude', 'delivery_city',
        'recipient_name', 'recipient_phone', 'recipient_email', 'description',
        'scheduled_pickup_at', 'scheduled_delivery_at', 'priority', 'delivery_fee', 'cod_amount',
        'requires_proof', 'delivery_notes',
    ];

    protected $hidden = ['otp_hash', 'tracking_token'];

    protected $attributes = ['status' => 'pending', 'priority' => 'normal', 'requires_proof' => true];

    protected function casts(): array
    {
        return [
            'status' => DeliveryStatus::class,
            'priority' => DeliveryPriority::class,
            'scheduled_pickup_at' => 'datetime',
            'scheduled_delivery_at' => 'datetime',
            'actual_pickup_at' => 'datetime',
            'actual_delivery_at' => 'datetime',
            'assigned_at' => 'datetime',
            'accepted_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'cod_collected_at' => 'datetime',
            'delivery_fee' => 'decimal:2',
            'cod_amount' => 'decimal:2',
            'cod_collected_amount' => 'decimal:2',
            'total_weight' => 'decimal:2',
            'distance_km' => 'decimal:2',
            'requires_proof' => 'boolean',
            'pickup_latitude' => 'float',
            'pickup_longitude' => 'float',
            'delivery_latitude' => 'float',
            'delivery_longitude' => 'float',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class)->withTrashed();
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class, 'assigned_driver_id')->withTrashed();
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'assigned_vehicle_id')->withTrashed();
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(Route::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(DeliveryItem::class);
    }

    public function statusHistory(): HasMany
    {
        return $this->hasMany(DeliveryStatusHistory::class)->orderBy('changed_at')->orderBy('id');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(DeliveryAssignment::class)->latest('id');
    }

    public function currentAssignment(): HasOne
    {
        return $this->hasOne(DeliveryAssignment::class)->whereIn('status', ['pending', 'accepted'])->latestOfMany();
    }

    public function proofs(): HasMany
    {
        return $this->hasMany(DeliveryProof::class);
    }

    public function invoiceItem(): HasOne
    {
        return $this->hasOne(InvoiceItem::class);
    }

    public function isOnTime(): ?bool
    {
        if ($this->status !== DeliveryStatus::Delivered || ! $this->scheduled_delivery_at || ! $this->actual_delivery_at) {
            return null;
        }

        return $this->actual_delivery_at->lte($this->scheduled_delivery_at);
    }
}

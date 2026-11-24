<?php

namespace App\Models;

use App\Enums\CompanyStatus;
use Database\Factories\CompanyFactory;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Company extends Model
{
    /** @use HasFactory<CompanyFactory> */
    use HasFactory, HasUlids, SoftDeletes;

    /** status, owner_id and suspension fields are set only by services. */
    protected $fillable = [
        'name', 'logo_path', 'registration_number', 'contact_email', 'phone',
        'country', 'city', 'address', 'timezone', 'currency',
    ];

    protected function casts(): array
    {
        return [
            'status' => CompanyStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /** ULID lives in public_id; the numeric id stays the primary key and is never exposed. */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(CompanySubscription::class)->latest('id');
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(CompanySubscription::class)->whereIn('status', ['trialing', 'active'])->latestOfMany();
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'company_users')
            ->using(CompanyUser::class)
            ->withPivot(['id', 'status', 'job_title', 'joined_at'])
            ->withTimestamps();
    }
}

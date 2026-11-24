<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use App\Enums\UserStatus;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, HasRoles, Notifiable, SoftDeletes;

    protected string $guard_name = 'web';

    /** Mirrors the column defaults so freshly created models are complete. */
    protected $attributes = [
        'status' => 'active',
        'is_super_admin' => false,
        'locale' => 'en',
    ];

    /** is_super_admin, status and current_company_id are deliberately NOT fillable. */
    protected $fillable = ['name', 'email', 'password', 'phone', 'locale'];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'is_super_admin' => 'boolean',
        ];
    }

    public function companies(): BelongsToMany
    {
        return $this->belongsToMany(Company::class, 'company_users')
            ->using(CompanyUser::class)
            ->withPivot(['id', 'status', 'job_title', 'joined_at'])
            ->withTimestamps();
    }

    public function activeCompanies(): BelongsToMany
    {
        return $this->companies()->wherePivot('status', MembershipStatus::Active->value);
    }

    public function currentCompany(): BelongsTo
    {
        return $this->belongsTo(Company::class, 'current_company_id');
    }

    public function isMemberOf(Company $company): bool
    {
        return $this->activeCompanies()->whereKey($company->id)->exists();
    }
}

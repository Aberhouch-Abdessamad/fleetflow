<?php

namespace App\Models;

use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** Platform defaults (company_id NULL) + company-specific types. */
class VehicleType extends Model
{
    protected $fillable = ['name', 'code', 'required_license', 'default_capacity', 'capacity_unit'];

    protected function casts(): array
    {
        return ['default_capacity' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::addGlobalScope('visible', function (Builder $q) {
            $tenant = app(TenantContext::class)->id();
            $q->where(fn ($w) => $w->whereNull('company_id')->when($tenant, fn ($x) => $x->orWhere('company_id', $tenant)));
        });
    }
}

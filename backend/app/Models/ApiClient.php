<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Sanctum\HasApiTokens;

/** A machine client (e-commerce store, WMS) scoped to one company. */
class ApiClient extends Model
{
    use BelongsToCompany, HasApiTokens;

    public const SCOPES = ['deliveries:create', 'deliveries:read', 'deliveries:cancel', 'shipments:write'];

    protected $fillable = ['name', 'type', 'scopes', 'is_active'];

    protected function casts(): array
    {
        return ['scopes' => 'array', 'is_active' => 'boolean', 'last_used_at' => 'datetime'];
    }

    public function webhookEndpoints(): HasMany
    {
        return $this->hasMany(WebhookEndpoint::class);
    }
}

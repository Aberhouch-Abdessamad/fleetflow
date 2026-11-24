<?php

namespace App\Http\Resources;

use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin User */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $tenant = app(TenantContext::class);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'email_verified' => $this->email_verified_at !== null,
            'is_super_admin' => (bool) $this->is_super_admin,
            'status' => $this->status->value,
            // Roles/permissions are those held in the CURRENT company only.
            'current_company' => $tenant->has() ? new CompanyResource($tenant->company()) : null,
            'roles' => $tenant->has() ? $this->getRoleNames()->values() : [],
            'permissions' => $tenant->has() ? $this->getAllPermissions()->pluck('name')->values() : [],
            'companies' => $this->whenLoaded('activeCompanies', fn () => $this->activeCompanies->map(fn ($c) => [
                'id' => $c->public_id,
                'name' => $c->name,
                'status' => $c->status->value,
            ])->values()),
        ];
    }
}

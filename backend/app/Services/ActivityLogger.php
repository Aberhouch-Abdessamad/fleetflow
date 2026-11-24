<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Support\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Model;

class ActivityLogger
{
    public function __construct(private readonly TenantContext $tenant) {}

    public function log(string $action, ?Model $subject = null, array $properties = [], ?int $companyId = null): ActivityLog
    {
        $request = request();

        return ActivityLog::create([
            'company_id' => $companyId ?? $this->tenant->id(),
            'user_id' => $request?->user()?->id,
            'action' => $action,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip_address' => $request?->ip(),
            'user_agent' => substr((string) $request?->userAgent(), 0, 255) ?: null,
        ]);
    }
}

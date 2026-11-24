<?php

namespace App\Services\Tenant;

use App\Models\Company;
use App\Models\CompanySetting;
use Illuminate\Support\Facades\Cache;

/** Typed, defaulted per-company operational settings. */
class CompanySettings
{
    public const DEFAULTS = [
        'exclusive_vehicle_assignment' => true,
        'drivers_can_reject_assignments' => true,
        'require_proof_of_delivery' => true,
        'require_delivery_otp' => false,
        'max_active_deliveries_per_driver' => 10,
        'public_tracking_enabled' => true,
        'tracking_show_driver_name' => true,
        'tracking_show_live_position' => true,
        'default_tax_rate' => 0,
        'invoice_due_days' => 30,
        'location_retention_days' => 90,
        'maintenance_reminder_days' => 14,
        'document_expiry_warning_days' => 30,
    ];

    public const RULES = [
        'exclusive_vehicle_assignment' => ['boolean'],
        'drivers_can_reject_assignments' => ['boolean'],
        'require_proof_of_delivery' => ['boolean'],
        'require_delivery_otp' => ['boolean'],
        'max_active_deliveries_per_driver' => ['integer', 'min:1', 'max:100'],
        'public_tracking_enabled' => ['boolean'],
        'tracking_show_driver_name' => ['boolean'],
        'tracking_show_live_position' => ['boolean'],
        'default_tax_rate' => ['numeric', 'min:0', 'max:100'],
        'invoice_due_days' => ['integer', 'min:0', 'max:365'],
        'location_retention_days' => ['integer', 'min:7', 'max:730'],
        'maintenance_reminder_days' => ['integer', 'min:1', 'max:120'],
        'document_expiry_warning_days' => ['integer', 'min:1', 'max:180'],
    ];

    public function all(Company $company): array
    {
        $stored = Cache::remember($this->key($company), 300, fn () => CompanySetting::where('company_id', $company->id)
            ->pluck('value', 'key')->all());

        return array_merge(self::DEFAULTS, array_intersect_key($stored, self::DEFAULTS));
    }

    public function get(Company $company, string $key): mixed
    {
        return $this->all($company)[$key] ?? null;
    }

    public function update(Company $company, array $values): array
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            CompanySetting::updateOrCreate(['company_id' => $company->id, 'key' => $key], ['value' => $value]);
        }
        Cache::forget($this->key($company));

        return $this->all($company);
    }

    private function key(Company $company): string
    {
        return "company:{$company->id}:settings";
    }
}

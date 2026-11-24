<?php

namespace App\Notifications;

class MaintenanceNotification extends TenantNotification
{
    public function __construct(?int $companyId, public int $vehicleId, public string $title, public ?string $body, public string $tone = 'warning')
    {
        parent::__construct($companyId);
    }

    protected function payload(): array
    {
        return ['title' => $this->title, 'body' => $this->body, 'category' => 'maintenance', 'tone' => $this->tone, 'vehicle_id' => $this->vehicleId, 'link' => "/app/vehicles/{$this->vehicleId}"];
    }
}

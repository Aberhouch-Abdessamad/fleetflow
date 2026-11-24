<?php

namespace App\Notifications;

class DeliveryStatusNotification extends TenantNotification
{
    public function __construct(?int $companyId, public int $deliveryId, public string $reference, public string $status, public ?string $reason)
    {
        parent::__construct($companyId);
    }

    protected function payload(): array
    {
        $label = str_replace('_', ' ', $this->status);

        return [
            'title' => "Delivery {$this->reference} is {$label}",
            'body' => $this->reason,
            'category' => 'delivery',
            'tone' => match ($this->status) {
                'delivered' => 'success', 'failed' => 'danger', default => 'info'
            },
            'delivery_id' => $this->deliveryId,
            'link' => "/app/deliveries/{$this->deliveryId}",
        ];
    }
}

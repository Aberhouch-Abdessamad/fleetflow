<?php

namespace App\Notifications;

class AssignmentNotification extends TenantNotification
{
    public function __construct(
        ?int $companyId,
        public int $deliveryId,
        public string $reference,
        public string $action, // assigned, reassigned, unassigned, accepted, rejected
        public ?string $driverName,
        public ?string $reason,
    ) {
        parent::__construct($companyId);
    }

    protected function payload(): array
    {
        $title = match ($this->action) {
            'assigned' => "New delivery {$this->reference} assigned to you",
            'reassigned' => "Delivery {$this->reference} reassigned to you",
            'unassigned' => "Delivery {$this->reference} was removed from your list",
            'accepted' => "{$this->driverName} accepted {$this->reference}",
            'rejected' => "{$this->driverName} rejected {$this->reference}",
            default => "Delivery {$this->reference} updated",
        };

        return [
            'title' => $title,
            'body' => $this->reason,
            'category' => 'assignment',
            'tone' => $this->action === 'rejected' ? 'warning' : 'info',
            'delivery_id' => $this->deliveryId,
            'link' => in_array($this->action, ['assigned', 'reassigned', 'unassigned'], true) ? '/driver' : "/app/deliveries/{$this->deliveryId}",
        ];
    }
}

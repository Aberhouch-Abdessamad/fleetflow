<?php

namespace App\Events;

class DeliveryStatusUpdated extends TenantBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'DeliveryStatusUpdated';
    }
}

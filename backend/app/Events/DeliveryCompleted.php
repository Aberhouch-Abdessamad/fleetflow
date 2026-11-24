<?php

namespace App\Events;

class DeliveryCompleted extends TenantBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'DeliveryCompleted';
    }
}

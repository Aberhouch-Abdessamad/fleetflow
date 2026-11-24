<?php

namespace App\Events;

class DeliveryExceptionReported extends TenantBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'DeliveryExceptionReported';
    }
}

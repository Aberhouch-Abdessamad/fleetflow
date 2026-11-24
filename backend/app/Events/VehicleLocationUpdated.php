<?php

namespace App\Events;

class VehicleLocationUpdated extends TenantBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'VehicleLocationUpdated';
    }
}

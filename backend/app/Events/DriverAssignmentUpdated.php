<?php

namespace App\Events;

class DriverAssignmentUpdated extends TenantBroadcastEvent
{
    public function broadcastAs(): string
    {
        return 'DriverAssignmentUpdated';
    }
}

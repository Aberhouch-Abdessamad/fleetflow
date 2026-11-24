<?php

namespace App\Policies;

use App\Models\Delivery;
use App\Models\Driver;
use App\Models\User;

/** Tenant isolation is already enforced by the global scope; this adds role rules. */
class DeliveryPolicy
{
    public function view(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.view') || $this->isAssignedDriver($user, $delivery);
    }

    public function update(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.update');
    }

    /** Drivers may only progress deliveries assigned to them. */
    public function progress(User $user, Delivery $delivery): bool
    {
        return $user->can('deliveries.update_own') && $this->isAssignedDriver($user, $delivery);
    }

    private function isAssignedDriver(User $user, Delivery $delivery): bool
    {
        return $delivery->assigned_driver_id !== null
            && Driver::whereKey($delivery->assigned_driver_id)->where('user_id', $user->id)->exists();
    }
}

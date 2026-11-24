<?php

namespace App\Policies;

use App\Models\Driver;
use App\Models\Expense;
use App\Models\User;

class ExpensePolicy
{
    public function view(User $user, Expense $e): bool
    {
        return $user->can('expenses.view') || $e->created_by === $user->id;
    }

    public function update(User $user, Expense $e): bool
    {
        return $user->can('expenses.manage') || ($e->created_by === $user->id && $user->can('expenses.create_own'));
    }

    public function review(User $user, Expense $e): bool
    {
        return $user->can('expenses.approve');
    }

    public function createFor(User $user, ?int $driverId): bool
    {
        if ($user->can('expenses.manage')) {
            return true;
        }

        return $user->can('expenses.create_own') && $driverId !== null && Driver::whereKey($driverId)->where('user_id', $user->id)->exists();
    }
}

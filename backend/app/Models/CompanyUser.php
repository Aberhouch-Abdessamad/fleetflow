<?php

namespace App\Models;

use App\Enums\MembershipStatus;
use Illuminate\Database\Eloquent\Relations\Pivot;

class CompanyUser extends Pivot
{
    protected $table = 'company_users';

    public $incrementing = true;

    protected function casts(): array
    {
        return [
            'status' => MembershipStatus::class,
            'joined_at' => 'datetime',
        ];
    }
}

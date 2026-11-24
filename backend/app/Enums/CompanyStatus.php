<?php

namespace App\Enums;

enum CompanyStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    /** Only active companies may operate (create data, dispatch, track). */
    public function isOperational(): bool
    {
        return $this === self::Active;
    }
}

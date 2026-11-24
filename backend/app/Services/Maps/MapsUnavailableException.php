<?php

namespace App\Services\Maps;

use App\Exceptions\DomainException;

class MapsUnavailableException extends DomainException
{
    public function __construct(string $message = 'No maps/routing provider is configured. Set MAPS_PROVIDER and an API key.')
    {
        parent::__construct($message, 'maps_provider_unavailable', 503);
    }
}

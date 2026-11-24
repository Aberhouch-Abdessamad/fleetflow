<?php

namespace App\Enums;

enum DeliveryPriority: string
{
    use EnumHelpers;

    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';
}

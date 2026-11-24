<?php

namespace App\Enums;

enum FuelType: string
{
    use EnumHelpers;

    case Petrol = 'petrol';
    case Diesel = 'diesel';
    case Electric = 'electric';
    case Hybrid = 'hybrid';
    case Lpg = 'lpg';
    case Cng = 'cng';
}

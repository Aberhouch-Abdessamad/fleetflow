<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    use EnumHelpers;

    case Draft = 'draft';
    case Issued = 'issued';
    case PartiallyPaid = 'partially_paid';
    case Paid = 'paid';
    case Void = 'void';
}

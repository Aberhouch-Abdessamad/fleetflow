<?php

namespace App\Enums;

enum PaymentMethod: string
{
    use EnumHelpers;

    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Card = 'card';
    case Cod = 'cod';
    case Cheque = 'cheque';
    case Other = 'other';
}

<?php

namespace App\Enums;

enum ProofType: string
{
    use EnumHelpers;

    case Photo = 'photo';
    case Signature = 'signature';
    case Otp = 'otp';
    case Confirmation = 'confirmation';
}

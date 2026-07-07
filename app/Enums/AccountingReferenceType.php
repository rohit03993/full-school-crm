<?php

namespace App\Enums;

enum AccountingReferenceType: string
{
    case Payment = 'payment';
    case FeePenalty = 'fee_penalty';
}

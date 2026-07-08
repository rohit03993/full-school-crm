<?php

namespace App\Enums;

enum AccountingReferenceType: string
{
    case Payment = 'payment';
    case FeePenalty = 'fee_penalty';
    case FeeMiscCharge = 'fee_misc_charge';
}

<?php

namespace App\Enums;

enum PaymentShortfallAction: string
{
    case CarryForward = 'carry_forward';
    case NewInstallment = 'new_installment';
    case SurplusForward = 'surplus_forward';

    public function label(): string
    {
        return match ($this) {
            self::CarryForward => 'Add balance to next installment',
            self::NewInstallment => 'Create new installment for balance',
            self::SurplusForward => 'Apply extra to future installments',
        };
    }
}

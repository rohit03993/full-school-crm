<?php

namespace App\Enums;

enum PaymentShortfallAction: string
{
    case CarryForward = 'carry_forward';
    case NewInstallment = 'new_installment';

    public function label(): string
    {
        return match ($this) {
            self::CarryForward => 'Add balance to next installment',
            self::NewInstallment => 'Create new installment for balance',
        };
    }
}

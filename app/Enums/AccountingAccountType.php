<?php

namespace App\Enums;

enum AccountingAccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Income = 'income';
    case Expense = 'expense';

    public function label(): string
    {
        return match ($this) {
            self::Asset => 'Asset',
            self::Liability => 'Liability',
            self::Income => 'Income',
            self::Expense => 'Expense',
        };
    }
}

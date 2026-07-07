<?php

namespace App\Filament\Forms;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;

class AddMiscChargeFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(): array
    {
        return [
            TextInput::make('label')
                ->label('Charge label')
                ->required()
                ->maxLength(100)
                ->placeholder('e.g. Exam fee, Study material'),
            TextInput::make('amount')
                ->label('Amount (₹)')
                ->numeric()
                ->required()
                ->minValue(1)
                ->step(1),
            DatePicker::make('due_date')
                ->label('Due date')
                ->native(false),
        ];
    }
}

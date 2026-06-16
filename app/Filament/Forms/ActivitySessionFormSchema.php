<?php

namespace App\Filament\Forms;

use App\Enums\BatchStatus;
use App\Models\Batch;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;

class ActivitySessionFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(string $titleField, string $dateField, ?string $titlePlaceholder = null): array
    {
        return [
            TextInput::make($titleField)
                ->label('Title')
                ->placeholder($titlePlaceholder ?? 'e.g. Front Office practical — customer handling')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            DatePicker::make($dateField)
                ->label('Date')
                ->required()
                ->native(false),
            Select::make('batch_id')
                ->label('Batch')
                ->options(fn (): array => Batch::query()
                    ->where('status', BatchStatus::Active)
                    ->with('course')
                    ->orderBy('name')
                    ->get()
                    ->mapWithKeys(fn (Batch $batch): array => [
                        $batch->id => $batch->selectLabel(),
                    ])
                    ->all())
                ->searchable()
                ->required()
                ->native(false),
        ];
    }
}

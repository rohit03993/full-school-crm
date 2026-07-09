<?php

namespace App\Filament\Forms;

use App\Enums\BatchStatus;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Support\EduExamLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;

class ActivitySessionFormSchema
{
    /**
     * @return array<int, \Filament\Forms\Components\Component>
     */
    public static function fields(): array
    {
        return [
            Select::make('activity_type_id')
                ->label('Exam type')
                ->options(fn (): array => ActivityType::scoringOptions())
                ->required()
                ->native(false)
                ->live()
                ->searchable()
                ->default(fn (): ?int => ActivityType::scoringTypes()->first()?->id)
                ->helperText('Exams only — use Exam windows to create tests from programme subjects.'),
            TextInput::make('title')
                ->label('Title')
                ->placeholder('e.g. Unit Test — Mathematics')
                ->required()
                ->maxLength(255)
                ->columnSpanFull(),
            DatePicker::make('session_date')
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
            ...self::dynamicMetadataFields(),
        ];
    }

    /**
     * @return array<int, TextInput|Textarea>
     */
    protected static function dynamicMetadataFields(): array
    {
        $types = ActivityType::scoringTypes()->keyBy('id');

        $fields = [];

        foreach ($types as $type) {
            foreach ($type->fields() as $field) {
                $key = (string) $field['key'];
                $component = match ($field['type'] ?? 'text') {
                    'textarea' => Textarea::make("metadata.{$key}")
                        ->label($field['label'])
                        ->rows(2),
                    'number' => TextInput::make("metadata.{$key}")
                        ->label($field['label'])
                        ->numeric(),
                    default => TextInput::make("metadata.{$key}")
                        ->label($field['label'])
                        ->maxLength(255),
                };

                $fields[] = $component
                    ->visible(fn (Get $get): bool => (int) $get('activity_type_id') === $type->id)
                    ->columnSpanFull();
            }
        }

        return $fields;
    }
}

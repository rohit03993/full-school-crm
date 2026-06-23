<?php

namespace App\Filament\Forms;

use App\Enums\BatchStatus;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Support\ActivityTypePresets;
use App\Support\EduExamLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                ->options(fn (): array => ActivityType::query()
                    ->enabled()
                    ->ordered()
                    ->pluck('name', 'id')
                    ->all())
                ->required()
                ->native(false)
                ->live()
                ->searchable()
                ->createOptionForm([
                    TextInput::make('name')
                        ->label('Type name')
                        ->placeholder('e.g. Unit Test, Exam, Mock Test')
                        ->required()
                        ->maxLength(100),
                    Toggle::make('tracks_marks')
                        ->label('Records marks & scores')
                        ->helperText('Enable if staff will enter marks when marking attendance (recommended for tests and exams).')
                        ->default(true),
                ])
                ->createOptionUsing(function (array $data): int {
                    $maxSort = (int) ActivityType::query()->max('sort_order');

                    return ActivityType::query()->create([
                        'name' => $data['name'],
                        'field_schema' => ($data['tracks_marks'] ?? true)
                            ? ActivityTypePresets::examMarksFields()
                            : [],
                        'sort_order' => $maxSort + 10,
                        'is_enabled' => true,
                    ])->id;
                })
                ->helperText(function (): ?string {
                    if (ActivityType::query()->enabled()->exists()) {
                        return 'Pick an existing type or choose **Create** in the list to add one (e.g. Unit Test, Exam).';
                    }

                    return 'No exam types yet — choose **Create** in the list, or add types under Academics → Exam Types.';
                }),
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
        $types = ActivityType::query()->enabled()->ordered()->get()->keyBy('id');

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

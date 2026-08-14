<?php

namespace App\Filament\Forms;

use App\Support\CommonCourseSubjects;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class CourseSubjectsFormSchema
{
    /**
     * Shared Subjects UI: common checklist + editable custom list.
     *
     * @return list<\Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            CheckboxList::make('common_subject_presets')
                ->label('Common subjects')
                ->options(fn (): array => CommonCourseSubjects::options())
                ->columns([
                    'default' => 2,
                    'md' => 3,
                    'xl' => 4,
                ])
                ->bulkToggleable()
                ->live()
                ->dehydrated(false)
                ->afterStateUpdated(function (Set $set, Get $get, mixed $state): void {
                    $selected = is_array($state) ? $state : [];
                    $existing = is_array($get('course_subjects')) ? $get('course_subjects') : [];

                    $set('course_subjects', CommonCourseSubjects::mergeIntoRows($selected, $existing));
                })
                ->helperText('Tick subjects to add them. Untick removes that subject from this programme on Save. Use “Add subject” below for anything not listed (e.g. Geography).')
                ->columnSpanFull(),
            Repeater::make('course_subjects')
                ->label('Subject list')
                ->schema([
                    TextInput::make('name')
                        ->label('Subject name')
                        ->placeholder('e.g. Geography, Fine Arts')
                        ->required()
                        ->maxLength(100),
                    TextInput::make('code')
                        ->label('Short code')
                        ->placeholder('e.g. GEO')
                        ->maxLength(30),
                    TextInput::make('default_max_marks')
                        ->label('Default max marks')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->placeholder('e.g. 100'),
                    Toggle::make('is_active')
                        ->label('Active')
                        ->default(true),
                ])
                ->columns(2)
                ->columnSpanFull()
                ->defaultItems(0)
                ->addActionLabel('Add subject')
                ->reorderable()
                ->helperText('Common subjects come from the checklist above. Add extra subjects here if needed.'),
        ];
    }
}

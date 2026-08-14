<?php

namespace App\Filament\Forms;

use App\Support\CommonCourseSubjects;
use App\Support\StaffOptions;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

class BatchSubjectsFormSchema
{
    /**
     * Section-specific subjects followed immediately by the teacher for each subject.
     *
     * @return list<\Filament\Forms\Components\Component|\Filament\Schemas\Components\Component>
     */
    public static function components(): array
    {
        return [
            CheckboxList::make('common_subject_presets')
                ->label('Subjects for this section')
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
                    $existing = is_array($get('section_subjects')) ? $get('section_subjects') : [];

                    $set('section_subjects', CommonCourseSubjects::mergeIntoSectionRows($selected, $existing));
                })
                ->helperText('Tick only the subjects taught in this section. Changing this list does not change any other section.')
                ->columnSpanFull(),
            Repeater::make('section_subjects')
                ->label('Selected subjects & teachers')
                ->schema([
                    Hidden::make('course_subject_id'),
                    TextInput::make('name')
                        ->label('Subject')
                        ->required()
                        ->maxLength(100)
                        ->placeholder('e.g. Geography'),
                    TextInput::make('code')
                        ->label('Code')
                        ->maxLength(30)
                        ->placeholder('e.g. GEO'),
                    TextInput::make('default_max_marks')
                        ->label('Max marks')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(1000)
                        ->placeholder('100'),
                    Select::make('user_id')
                        ->label('Teacher')
                        ->options(fn (): array => StaffOptions::facultyOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Not assigned'),
                ])
                ->columns(4)
                ->columnSpanFull()
                ->defaultItems(0)
                ->addActionLabel('Add another subject')
                ->reorderable()
                ->helperText('Assign a staff teacher beside each selected subject. Teacher is optional and can be added later.'),
        ];
    }
}

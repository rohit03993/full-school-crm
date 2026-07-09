<?php

namespace App\Filament\Forms;

use App\Enums\Gender;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Support\BatchSelectOptions;
use App\Support\StudentLabels;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\HtmlString;

class AddStudentFormSchema
{
    /**
     * @return array<string, mixed>
     */
    public static function initialState(): array
    {
        return [
            'academic_session_id' => AcademicSession::current()?->id,
            'batch_id' => null,
            'roll_number' => '',
            'name' => '',
            'father_name' => null,
            'mobile' => null,
            'date_of_birth' => null,
            'gender' => null,
        ];
    }

    /**
     * @return array<int, Section|Placeholder>
     */
    public static function fields(): array
    {
        return [
            Placeholder::make('add_student_intro')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-400">'
                    .'Register one student and enroll them immediately — course fee is taken from the batch. '
                    .'You can collect fees and upload documents from the student profile after saving.'
                    .'</p>'
                ))
                ->columnSpanFull(),
            Section::make('Class & batch')
                ->description('Course is set automatically from the batch you choose.')
                ->columns(2)
                ->schema([
                    Select::make('academic_session_id')
                        ->label('Academic session')
                        ->options(fn (): array => AcademicSession::query()
                            ->where('is_active', true)
                            ->orderByDesc('starts_on')
                            ->pluck('name', 'id')
                            ->all())
                        ->default(fn (): ?int => AcademicSession::current()?->id)
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(fn (Set $set): mixed => $set('batch_id', null)),
                    Select::make('batch_id')
                        ->label('Class & section')
                        ->options(fn (Get $get): array => BatchSelectOptions::forSession((int) ($get('academic_session_id') ?? 0)))
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->helperText('Student is assigned to this batch on save.'),
                    Placeholder::make('course_preview')
                        ->label('Course')
                        ->content(function (Get $get): string {
                            $batchId = (int) ($get('batch_id') ?? 0);

                            if ($batchId <= 0) {
                                return 'Select a batch to see the course.';
                            }

                            $batch = Batch::query()->with('course')->find($batchId);

                            if (! $batch?->course) {
                                return '—';
                            }

                            $fee = (float) $batch->course->fee;

                            return $batch->course->name
                                .($fee > 0 ? ' · Fee ₹'.number_format($fee, 2) : ' · No fee set on course');
                        })
                        ->columnSpanFull(),
                    TextInput::make('roll_number')
                        ->label(StudentLabels::rollNumberLabel())
                        ->required()
                        ->maxLength(50)
                        ->extraInputAttributes(['class' => 'font-mono uppercase'])
                        ->helperText('Must be unique across all students.')
                        ->columnSpanFull(),
                ]),
            Section::make('Student details')
                ->description('Only name is required. Add mobile now if you have it — portal login uses date of birth when mobile is missing.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')
                        ->label('Student name')
                        ->required()
                        ->maxLength(255)
                        ->autofocus()
                        ->columnSpanFull(),
                    TextInput::make('father_name')
                        ->label("Father's name")
                        ->maxLength(255),
                    TextInput::make('mobile')
                        ->label('Mobile')
                        ->tel()
                        ->maxLength(15)
                        ->helperText('Optional. 10-digit Indian number. If this mobile already exists, the existing record is updated.'),
                    DatePicker::make('date_of_birth')
                        ->label('Date of birth')
                        ->maxDate(now()->subDay())
                        ->native(false),
                    Select::make('gender')
                        ->options(collect(Gender::cases())->mapWithKeys(
                            fn (Gender $gender): array => [$gender->value => $gender->label()],
                        )->all())
                        ->native(false),
                ]),
        ];
    }
}

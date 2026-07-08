<?php

namespace App\Filament\Pages;

use App\Enums\BatchShift;
use App\Enums\CrmPermission;
use App\Enums\DurationType;
use App\Filament\Resources\Batches\BatchResource;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Services\ClassSectionService;
use App\Support\ClassSectionLabel;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use App\Support\InstituteTerminology;
use App\Support\StaffOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Concerns\CanUseDatabaseTransactions;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class AddClassSectionPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPlusCircle;

    protected static ?string $navigationLabel = 'Add class & section';

    protected static ?string $title = 'Add class & section';

    protected static ?string $slug = 'add-class-section';

    protected static ?int $navigationSort = 15;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage);
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('class_section.create');
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('class_section.create');
    }

    public function mount(): void
    {
        $this->form->fill([
            'programme_mode' => 'existing',
            'academic_session_id' => AcademicSession::current()?->id,
            'duration' => 1,
            'duration_type' => DurationType::Years->value,
            'fee' => 0,
            'show_on_website' => true,
            'course_subjects' => [],
            'subject_teacher_assignments' => [],
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All classes & sections')
                ->url(ClassSectionsPage::getUrl())
                ->color('gray'),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $courseLabel = InstituteTerminology::label('course');
        $batchLabel = InstituteTerminology::label('batch');

        return $schema->components([
            Placeholder::make('intro')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-400">'
                    ."Create <strong>{$courseLabel}</strong> and <strong>{$batchLabel}</strong> together — e.g. Class 12 + Section A, or IIT JEE Class 12 + Batch A."
                    .'</p>'
                ))
                ->columnSpanFull(),
            Section::make('Programme / class')
                ->description("The programme students enroll in (fee and subjects). All sections share this {$courseLabel}.")
                ->schema([
                    Select::make('programme_mode')
                        ->label('Programme')
                        ->options([
                            'existing' => 'Use existing '.strtolower($courseLabel),
                            'new' => 'Create new '.strtolower($courseLabel),
                        ])
                        ->default('existing')
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('course_id')
                        ->label($courseLabel)
                        ->options(fn (): array => InstituteProfile::activeCourseAdmissionOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'existing')
                        ->required(fn (Get $get): bool => $get('programme_mode') === 'existing')
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?int $state) => $this->applySubjectTeacherRows($set, $state)),
                    TextInput::make('programme_name')
                        ->label($courseLabel.' name')
                        ->placeholder('e.g. Class 12 Science, IIT JEE Class 12')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new')
                        ->required(fn (Get $get): bool => $get('programme_mode') === 'new')
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (Set $set, Get $get, ?string $state): void {
                            if (blank($get('programme_code'))) {
                                $set('programme_code', ClassSectionLabel::suggestCourseCode((string) $state));
                            }

                            $this->applySuggestedBatchName($set, $get);
                        }),
                    TextInput::make('programme_code')
                        ->label('Programme code')
                        ->maxLength(50)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new')
                        ->required(fn (Get $get): bool => $get('programme_mode') === 'new'),
                    TextInput::make('duration')
                        ->label('Duration')
                        ->numeric()
                        ->minValue(1)
                        ->default(1)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new'),
                    Select::make('duration_type')
                        ->label('Duration unit')
                        ->options(collect(DurationType::cases())->mapWithKeys(
                            fn (DurationType $type): array => [$type->value => $type->label()],
                        ))
                        ->default(DurationType::Years->value)
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new'),
                    TextInput::make('fee')
                        ->label('Programme fee')
                        ->numeric()
                        ->prefix('₹')
                        ->minValue(0)
                        ->default(0)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new'),
                    Toggle::make('show_on_website')
                        ->label('Show on public website')
                        ->default(true)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new'),
                    Textarea::make('description')
                        ->label('Description')
                        ->rows(3)
                        ->columnSpanFull()
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'new'),
                ])
                ->columns(2),
            Section::make('Subjects')
                ->description('Optional. Shared by every section under this programme.')
                ->schema([
                    Repeater::make('course_subjects')
                        ->label('Subjects')
                        ->schema([
                            TextInput::make('name')
                                ->label('Subject name')
                                ->required()
                                ->maxLength(100),
                            TextInput::make('code')
                                ->label('Short code')
                                ->maxLength(30),
                            TextInput::make('default_max_marks')
                                ->label('Default max marks')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(1000),
                            Toggle::make('is_active')
                                ->label('Active')
                                ->default(true),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addActionLabel('Add subject')
                        ->reorderable(),
                ])
                ->collapsed()
                ->visible(fn (Get $get): bool => $get('programme_mode') === 'new')
                ->columnSpanFull(),
            Section::make('Section / batch')
                ->description("Where students attend — e.g. Section A, Batch Morning.")
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
                        ->native(false),
                    TextInput::make('section')
                        ->label('Section / batch label')
                        ->placeholder('e.g. A, B, Morning, Batch A')
                        ->required()
                        ->maxLength(50)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (Set $set, Get $get) => $this->applySuggestedBatchName($set, $get)),
                    TextInput::make('batch_name')
                        ->label('Internal batch name')
                        ->helperText('Used in reports. Auto-filled from programme + section; you can edit.')
                        ->maxLength(255),
                    Select::make('shift')
                        ->label('Shift')
                        ->options(collect(BatchShift::cases())->mapWithKeys(
                            fn (BatchShift $shift): array => [$shift->value => $shift->label()],
                        ))
                        ->native(false),
                    Select::make('trainer_user_id')
                        ->label('Faculty / trainer')
                        ->options(fn (): array => StaffOptions::facultyOptions())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->helperText('Used for attendance and batch ownership (existing behaviour).'),
                    DatePicker::make('start_date')
                        ->label('Start date')
                        ->native(false),
                    DatePicker::make('end_date')
                        ->label('End date')
                        ->afterOrEqual('start_date')
                        ->native(false),
                    Placeholder::make('preview_label')
                        ->label('Will appear as')
                        ->content(function (Get $get): string {
                            $programme = $this->previewProgrammeName($get);

                            if (blank($programme)) {
                                return 'Enter programme and section to preview.';
                            }

                            $section = trim((string) $get('section'));

                            return filled($section)
                                ? "{$programme} · Section {$section}"
                                : $programme;
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Staff assignments')
                ->description('Optional. Class/batch lead and subject teachers.')
                ->schema([
                    Select::make('lead_teacher_user_id')
                        ->label('Class / batch lead teacher')
                        ->options(fn (): array => StaffOptions::facultyOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Not assigned'),
                    Repeater::make('subject_teacher_assignments')
                        ->label('Subject teachers')
                        ->schema([
                            Hidden::make('course_subject_id'),
                            TextInput::make('subject_name')
                                ->label('Subject')
                                ->disabled()
                                ->dehydrated(false),
                            Select::make('user_id')
                                ->label('Teacher')
                                ->options(fn (): array => StaffOptions::facultyOptions())
                                ->searchable()
                                ->native(false)
                                ->placeholder('Not assigned'),
                        ])
                        ->columns(2)
                        ->columnSpanFull()
                        ->defaultItems(0)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'existing' && filled($get('course_id'))),
                ])
                ->collapsed()
                ->columns(2),
        ]);
    }

    public function save(): void
    {
        try {
            $result = app(ClassSectionService::class)->create($this->form->getState());
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not save')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Please check the form.')
                ->danger()
                ->send();

            throw $exception;
        }

        $batch = $result['batch'];
        $label = ClassSectionLabel::forBatch($batch);

        Notification::make()
            ->title('Class & section created')
            ->body("{$label} is ready. Enroll students or assign more teachers anytime.")
            ->success()
            ->actions([
                \Filament\Actions\Action::make('edit_batch')
                    ->label('Open section')
                    ->url(BatchResource::getUrl('edit', ['record' => $batch])),
                \Filament\Actions\Action::make('add_another')
                    ->label('Add another')
                    ->action(fn () => $this->mount()),
            ])
            ->send();

        $this->redirect(ClassSectionsPage::getUrl());
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            $this->getFormContentComponent(),
        ]);
    }

    public function getFormContentComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('form')])
            ->id('form')
            ->livewireSubmitHandler('save')
            ->footer([
                Actions::make([
                    \Filament\Actions\Action::make('save')
                        ->label('Create class & section')
                        ->submit('save'),
                ]),
            ]);
    }

    protected function applySubjectTeacherRows(Set $set, ?int $courseId): void
    {
        if (! $courseId) {
            $set('subject_teacher_assignments', []);

            return;
        }

        $rows = CourseSubject::query()
            ->where('course_id', $courseId)
            ->active()
            ->ordered()
            ->get()
            ->map(fn (CourseSubject $subject): array => [
                'course_subject_id' => $subject->id,
                'subject_name' => $subject->displayLabel(),
                'user_id' => null,
            ])
            ->values()
            ->all();

        $set('subject_teacher_assignments', $rows);
    }

    protected function applySuggestedBatchName(Set $set, Get $get): void
    {
        $programme = $this->previewProgrammeName($get);
        $section = trim((string) $get('section'));

        if (blank($programme) || $section === '') {
            return;
        }

        $set('batch_name', ClassSectionLabel::suggestBatchName($programme, $section));
    }

    protected function previewProgrammeName(Get $get): string
    {
        if ($get('programme_mode') === 'new') {
            return trim((string) $get('programme_name'));
        }

        $courseId = (int) ($get('course_id') ?? 0);

        if ($courseId <= 0) {
            return '';
        }

        $course = Course::query()->find($courseId);

        return $course?->name ?? '';
    }
}

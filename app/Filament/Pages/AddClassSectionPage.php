<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\DurationType;
use App\Filament\Forms\BatchSubjectsFormSchema;
use App\Filament\Resources\Batches\BatchResource;
use App\Models\AcademicSession;
use App\Models\Course;
use App\Services\BatchSubjectService;
use App\Services\ClassSectionService;
use App\Support\ClassSectionLabel;
use App\Support\CommonCourseSubjects;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use App\Support\InstituteTerminology;
use App\Support\StaffOptions;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
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
            'common_subject_presets' => [],
            'section_subjects' => [],
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
                ->description("The programme students enroll in. Subjects are selected separately for this {$batchLabel}.")
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
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('course_id', null);
                            $set('common_subject_presets', []);
                            $set('section_subjects', []);
                        }),
                    Select::make('course_id')
                        ->label($courseLabel)
                        ->options(fn (): array => InstituteProfile::activeCourseAdmissionOptions())
                        ->searchable()
                        ->native(false)
                        ->visible(fn (Get $get): bool => $get('programme_mode') === 'existing')
                        ->required(fn (Get $get): bool => $get('programme_mode') === 'existing')
                        ->live()
                        ->afterStateUpdated(fn (Set $set, ?int $state) => $this->applySectionSubjectRows($set, $state)),
                    TextInput::make('programme_name')
                        ->label($courseLabel.' name')
                        ->placeholder('e.g. Class 12 Science, IIT JEE Class 12')
                        ->maxLength(255)
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
                        ->live(onBlur: true),
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
            Section::make('Subjects & teachers for this section')
                ->description('Choose subjects for this section only. Then select the staff teacher beside each subject.')
                ->schema([
                    Select::make('lead_teacher_user_id')
                        ->label('Class / batch lead teacher')
                        ->options(fn (): array => StaffOptions::facultyOptions())
                        ->searchable()
                        ->native(false)
                        ->placeholder('Not assigned'),
                    ...BatchSubjectsFormSchema::components(),
                ])
                ->visible(fn (Get $get): bool => $get('programme_mode') === 'new' || filled($get('course_id')))
                ->columns(1),
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

    protected function applySectionSubjectRows(Set $set, ?int $courseId): void
    {
        $rows = app(BatchSubjectService::class)->suggestedRowsForCourse($courseId);
        $set('section_subjects', $rows);
        $set('common_subject_presets', CommonCourseSubjects::keysMatchingRows($rows));
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

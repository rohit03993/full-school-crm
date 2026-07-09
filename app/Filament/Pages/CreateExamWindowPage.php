<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\CourseSubject;
use App\Services\ExamWindowService;
use App\Support\BatchSelectOptions;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\FeatureGate;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
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
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;

class CreateExamWindowPage extends Page
{
    use CanUseDatabaseTransactions;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = null;

    protected static ?string $slug = 'create-exam-window';

    public function getTitle(): string
    {
        return CrmMenuLabels::createExam();
    }

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::AcademicsManage);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('exam_windows.create');
    }

    public function mount(): void
    {
        $this->form->fill([
            'session_date' => now()->toDateString(),
            'open_immediately' => true,
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('All exam windows')
                ->url(ExamWindowsPage::getUrl())
                ->color('gray'),
        ];
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Placeholder::make('intro')
                ->label('')
                ->content(new HtmlString(
                    '<p class="text-sm text-gray-600 dark:text-gray-400">'
                    .'Pick a <strong>section</strong> and exam details. Subject rows are taken from the programme — one mark-entry sheet per subject.'
                    .'</p>'
                ))
                ->columnSpanFull(),
            Section::make('Exam details')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class / section')
                        ->options(fn (): array => BatchSelectOptions::activeOptions())
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live(),
                    Select::make('activity_type_id')
                        ->label('Exam type')
                        ->options(fn (): array => ActivityType::query()
                            ->enabled()
                            ->ordered()
                            ->get()
                            ->filter(fn (ActivityType $type): bool => $type->supportsScoring())
                            ->mapWithKeys(fn (ActivityType $type): array => [$type->id => $type->name])
                            ->all())
                        ->required()
                        ->native(false),
                    TextInput::make('test_name')
                        ->label('Exam name')
                        ->placeholder('e.g. Unit Test 1, Half Yearly')
                        ->required()
                        ->maxLength(120),
                    DatePicker::make('session_date')
                        ->label('Exam date')
                        ->required()
                        ->native(false)
                        ->default(now()),
                    Toggle::make('open_immediately')
                        ->label('Open for teachers immediately')
                        ->default(true)
                        ->helperText('When on, subject teachers can enter marks right away. When off, stays as draft until you open it.'),
                    Textarea::make('remarks')
                        ->label('Notes for staff')
                        ->rows(2)
                        ->columnSpanFull(),
                    Placeholder::make('subjects_preview')
                        ->label('Subjects from programme')
                        ->content(function (Get $get): string {
                            $batchId = (int) ($get('batch_id') ?? 0);

                            if ($batchId <= 0) {
                                return 'Select a section to preview subjects.';
                            }

                            $batch = Batch::query()->with('course.subjects')->find($batchId);
                            $subjects = $batch?->course?->subjects?->filter(fn (CourseSubject $s): bool => $s->is_active) ?? collect();

                            if ($subjects->isEmpty()) {
                                return 'No subjects on this programme — add subjects under Programme & fee first.';
                            }

                            return $subjects
                                ->map(fn (CourseSubject $s): string => $s->displayLabel().' (max '.($s->default_max_marks ?? 100).')')
                                ->implode(' · ');
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public function save(): void
    {
        try {
            $window = app(ExamWindowService::class)->create($this->form->getState(), Auth::user());
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Could not create exam')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Check the form.')
                ->danger()
                ->send();

            throw $exception;
        }

        Notification::make()
            ->title('Exam window created')
            ->body("{$window->test_name} is ready with {$window->subjects->count()} subject(s).")
            ->success()
            ->send();

        $this->redirect(ExamWindowPage::getUrl(['window' => $window->id]));
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
                    Action::make('save')
                        ->label('Create exam window')
                        ->icon(Heroicon::OutlinedPlus)
                        ->submit('save'),
                ]),
            ]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Services\HomeworkSubmissionService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use UnitEnum;

class SubmitHomeworkPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'Homework';

    protected static ?int $navigationSort = 44;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::submitHomework();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        $user = Auth::user();

        return $user !== null && app(HomeworkSubmissionService::class)->canSubmit($user);
    }

    public function getSubheading(): ?string
    {
        return 'Your classes for the selected date. Add homework for empty subjects. After you submit, you can mark Done or Not done. Admin will still review and send one WhatsApp to parents.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'course_subject_id' => null,
            'homework_date' => now()->toDateString(),
            'title' => '',
            'description' => '',
            'attachment' => null,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $service = app(HomeworkSubmissionService::class);
        $user = Auth::user();

        return $schema->components([
            Section::make('Date')
                ->description('Today is selected. Pick a past date only if you need to add a missed day.')
                ->schema([
                    DatePicker::make('homework_date')
                        ->label('Homework date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->live(),
                ])
                ->columns(2),
            Section::make('My classes')
                ->description('Only classes and subjects assigned to you.')
                ->schema([
                    View::make('filament.pages.partials.submit-homework')
                        ->viewData(function () use ($service, $user): array {
                            $date = $this->dateString();
                            $batchId = (int) ($this->data['batch_id'] ?? 0);
                            $subjectId = (int) ($this->data['course_subject_id'] ?? 0);

                            return [
                                'desk' => $user
                                    ? $service->teacherDeskForDate($user, $date)
                                    : ['counts' => ['missing' => 0, 'submitted' => 0, 'approved' => 0, 'sent' => 0], 'groups' => []],
                                'selectedBatchId' => $batchId,
                                'selectedSubjectId' => $subjectId,
                                'ready' => $batchId > 0 && $subjectId > 0,
                                'dateLabel' => Carbon::parse($date)->format('d M Y'),
                                'isToday' => $date === now()->toDateString(),
                                'checkBaseUrl' => HomeworkCheckPage::getUrl(),
                                'checkDate' => $date,
                            ];
                        })
                        ->columnSpanFull(),
                ]),
            Section::make('Pick a class')
                ->description('Use this if Add homework did not open the form.')
                ->collapsed()
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $user ? $service->batchOptionsFor($user) : [])
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function () use ($service, $user): void {
                            $this->data['course_subject_id'] = null;
                            $this->autoSelectSubject($service, $user);
                        }),
                    Select::make('course_subject_id')
                        ->label('Subject')
                        ->options(function () use ($service, $user): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);

                            return ($batchId > 0 && $user) ? $service->subjectOptionsForBatch($user, $batchId) : [];
                        })
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                ])
                ->columns(2),
            Section::make('Homework details')
                ->description('Attach a PDF/image or type the homework, then submit to admin.')
                ->schema([
                    TextInput::make('title')
                        ->label('Title (optional)')
                        ->placeholder('e.g. Chapter 5 – Q1 to Q10')
                        ->maxLength(255),
                    Textarea::make('description')
                        ->label('Homework details')
                        ->placeholder('Type the homework, or attach a file below.')
                        ->rows(4)
                        ->columnSpanFull(),
                    FileUpload::make('attachment')
                        ->label('PDF or image (optional)')
                        ->disk('public')
                        ->directory('homework')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'image/jpeg',
                            'image/png',
                            'image/webp',
                        ])
                        ->maxSize(10240)
                        ->columnSpanFull(),
                    Actions::make([
                        Action::make('submitHomework')
                            ->label('Submit to admin')
                            ->color('primary')
                            ->action('submit'),
                    ])->columnSpanFull(),
                ])
                ->columns(2)
                ->visible(fn (): bool => filled($this->data['batch_id'] ?? null) && filled($this->data['course_subject_id'] ?? null)),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('submitHomeworkForm'),
        ]);
    }

    public function submit(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $state = $this->form->getState();

        if ((int) ($state['batch_id'] ?? 0) < 1 || (int) ($state['course_subject_id'] ?? 0) < 1) {
            Notification::make()->title('Pick a class and subject first')->warning()->send();

            return;
        }

        try {
            $assignment = app(HomeworkSubmissionService::class)->submit($user, [
                'batch_id' => (int) ($state['batch_id'] ?? 0),
                'course_subject_id' => (int) ($state['course_subject_id'] ?? 0),
                'homework_date' => $this->dateString(),
                'title' => (string) ($state['title'] ?? ''),
                'description' => (string) ($state['description'] ?? ''),
                'file_path' => $this->attachmentPath($state['attachment'] ?? null),
            ]);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not submit.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->data['title'] = '';
        $this->data['description'] = '';
        $this->data['attachment'] = null;

        Notification::make()
            ->title('Submitted to admin')
            ->body($assignment->courseSubject?->displayLabel().' homework submitted for '.Carbon::parse($this->dateString())->format('d M Y').'. Admin will review and send it to parents.')
            ->success()
            ->send();
    }

    public function startAdd(int $batchId, int $subjectId): void
    {
        if ($batchId < 1 || $subjectId < 1) {
            return;
        }

        $this->form->fill([
            ...($this->data ?? []),
            'batch_id' => $batchId,
            'course_subject_id' => $subjectId,
            'title' => '',
            'description' => '',
            'attachment' => null,
        ]);
    }

    public function deleteSubmission(int $assignmentId): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            app(HomeworkSubmissionService::class)->deleteSubmission($user, $assignmentId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not remove.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()->title('Homework removed')->success()->send();
    }

    protected function attachmentPath(mixed $attachment): ?string
    {
        if (is_array($attachment)) {
            $first = $attachment[array_key_first($attachment)] ?? reset($attachment);

            return filled($first) ? (string) $first : null;
        }

        return filled($attachment) ? (string) $attachment : null;
    }

    protected function dateString(): string
    {
        $value = $this->data['homework_date'] ?? null;

        return filled($value) ? Carbon::parse((string) $value)->toDateString() : now()->toDateString();
    }

    protected function autoSelectSubject(HomeworkSubmissionService $service, mixed $user): void
    {
        if (! $user || ! filled($this->data['batch_id'] ?? null)) {
            return;
        }

        $options = $service->subjectOptionsForBatch($user, (int) $this->data['batch_id']);

        if (count($options) === 1) {
            $this->data['course_subject_id'] = (int) array_key_first($options);
        }
    }
}

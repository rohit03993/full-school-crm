<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Services\HomeworkSubmissionService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
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
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static ?string $title = 'Submit Homework';

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
        return 'Pick your class and subject, add today\'s homework, then submit it to admin. Admin combines all subjects and sends one WhatsApp to parents.';
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
            Section::make('Homework details')
                ->description('Subject auto-fills when you teach only one for the class. Attach a PDF/image or type the homework.')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $user ? $service->batchOptionsFor($user) : [])
                        ->searchable()
                        ->required()
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
                        ->required()
                        ->native(false)
                        ->live()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    DatePicker::make('homework_date')
                        ->label('Homework date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    TextInput::make('title')
                        ->label('Title (optional)')
                        ->placeholder('e.g. Chapter 5 – Q1 to Q10')
                        ->maxLength(255)
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    Textarea::make('description')
                        ->label('Homework details')
                        ->placeholder('Type the homework, or attach a file below.')
                        ->rows(4)
                        ->columnSpanFull()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
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
                        ->columnSpanFull()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                ])
                ->columns(3),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('submitHomeworkForm'),
            View::make('filament.pages.partials.submit-homework')
                ->viewData(function (): array {
                    $user = Auth::user();
                    $batchId = (int) ($this->data['batch_id'] ?? 0);

                    return [
                        'ready' => $batchId > 0 && filled($this->data['course_subject_id'] ?? null),
                        'submissions' => ($user && $batchId > 0)
                            ? app(HomeworkSubmissionService::class)->submissionsForTeacher($user, $batchId, $this->dateString())
                            : collect(),
                        'dateLabel' => Carbon::parse($this->dateString())->format('d M Y'),
                    ];
                }),
        ]);
    }

    public function submit(): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        $state = $this->form->getState();

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

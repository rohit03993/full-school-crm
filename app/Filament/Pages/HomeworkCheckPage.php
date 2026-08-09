<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\HomeworkCheckService;
use App\Support\CrmNavigation;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
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

class HomeworkCheckPage extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::HomeworkManage;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::Homework;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Homework check';

    protected static ?string $title = 'Homework Check';

    protected static ?int $navigationSort = 46;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    /** @var list<int|string> */
    public array $selectedStudentIds = [];

    public function getSubheading(): ?string
    {
        return 'Select class, subject and date, then mark the list. WhatsApp is sent only for Not Done. Failed sends can be resent.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'course_subject_id' => null,
            'check_date' => now()->toDateString(),
            'homework_assignment_id' => null,
            'topic' => "Today's homework",
            'student_search' => '',
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        $service = app(HomeworkCheckService::class);
        $user = Auth::user();

        return $schema->components([
            Section::make('Class & subject')
                ->description('Choose the class, subject and date. The student list opens after the subject is selected.')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $user ? $service->batchOptionsFor($user) : [])
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->data['course_subject_id'] = null;
                            $this->data['homework_assignment_id'] = null;
                            $this->selectedStudentIds = [];
                        }),
                    Select::make('course_subject_id')
                        ->label('Subject')
                        ->options(function () use ($service, $user): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);
                            if ($batchId < 1 || ! $user) {
                                return [];
                            }

                            return $service->subjectOptionsForBatch($user, $batchId);
                        })
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null))
                        ->afterStateUpdated(function (): void {
                            $this->selectedStudentIds = [];
                        }),
                    DatePicker::make('check_date')
                        ->label('Check date')
                        ->native(false)
                        ->required()
                        ->maxDate(now())
                        ->live()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null))
                        ->afterStateUpdated(function (): void {
                            $this->selectedStudentIds = [];
                        }),
                    Select::make('homework_assignment_id')
                        ->label('Link portal homework (optional)')
                        ->options(function () use ($service): array {
                            $batchId = (int) ($this->data['batch_id'] ?? 0);

                            return $batchId > 0 ? $service->assignmentOptionsForBatch($batchId) : [];
                        })
                        ->searchable()
                        ->native(false)
                        ->live()
                        ->placeholder('No linked assignment')
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null))
                        ->afterStateUpdated(function ($state) use ($service): void {
                            if (! filled($state)) {
                                return;
                            }

                            $options = $service->assignmentOptionsForBatch((int) ($this->data['batch_id'] ?? 0));
                            $label = $options[(int) $state] ?? null;

                            if (filled($label)) {
                                $this->data['topic'] = explode(' · ', (string) $label)[0];
                            }
                        }),
                    Textarea::make('topic')
                        ->label('Homework topic (optional)')
                        ->helperText('Included in the WhatsApp message. Defaults to portal homework title or “Today\'s homework”.')
                        ->placeholder("e.g. Chapter 5 – Complete Questions 1 to 10")
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null)),
                    TextInput::make('student_search')
                        ->label('Filter students')
                        ->placeholder('Type a name…')
                        ->live(debounce: 300)
                        ->visible(fn (): bool => $this->rosterReady()),
                ])
                ->columns(3),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('homeworkCheckForm'),
            View::make('filament.pages.partials.homework-check-actions')
                ->viewData(function (): array {
                    $students = $this->rosterStudents();
                    $summary = app(HomeworkCheckService::class)->daySummaryFromRoster($students);

                    return [
                        'rosterReady' => $this->rosterReady(),
                        'students' => $students,
                        'selectedStudentIds' => $this->selectedStudentIds,
                        'checkDateLabel' => $this->checkDateLabel(),
                        'summary' => $summary,
                        'unmarkedCount' => $summary['unmarked'],
                        'recent' => filled($this->data['batch_id'] ?? null)
                            ? app(HomeworkCheckService::class)->recentForBatch(
                                (int) $this->data['batch_id'],
                                15,
                                $this->checkDate(),
                            )
                            : collect(),
                    ];
                }),
        ]);
    }

    public function toggleSelectAll(): void
    {
        $ids = $this->rosterStudents()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        if (count($this->selectedStudentIds) === count($ids) && count($ids) > 0) {
            $this->selectedStudentIds = [];

            return;
        }

        $this->selectedStudentIds = $ids;
    }

    public function markStudentDone(int $studentId, HomeworkCheckService $service): void
    {
        $this->markOne($service, $studentId, HomeworkCheckStatus::Done);
    }

    public function markStudentNotDone(int $studentId, HomeworkCheckService $service): void
    {
        $this->markOne($service, $studentId, HomeworkCheckStatus::NotDone);
    }

    public function markSelectedNotDone(HomeworkCheckService $service): void
    {
        $user = Auth::user();

        if (! $user || ! $this->rosterReady()) {
            Notification::make()->title('Select class and subject first')->warning()->send();

            return;
        }

        $ids = collect($this->selectedStudentIds)->map(fn ($id): int => (int) $id)->filter()->unique()->values()->all();

        if ($ids === []) {
            Notification::make()->title('Select at least one student')->warning()->send();

            return;
        }

        $result = $service->markMany(
            $user,
            (int) $this->data['batch_id'],
            $ids,
            (int) $this->data['course_subject_id'],
            (string) ($this->data['topic'] ?? ''),
            HomeworkCheckStatus::NotDone,
            $this->checkDate(),
            $this->assignmentId(),
        );

        $this->selectedStudentIds = [];

        Notification::make()
            ->title('Marked Not Done')
            ->body(
                $result['marked'].' student(s). WhatsApp queued: '.$result['whatsappQueued']
                .($result['whatsappFailed'] > 0 ? ', failed: '.$result['whatsappFailed'] : '')
                .($result['errors'] !== [] ? '. '.implode(' ', array_slice($result['errors'], 0, 2)) : '')
            )
            ->success()
            ->send();
    }

    public function markRemainingDone(HomeworkCheckService $service): void
    {
        $user = Auth::user();

        if (! $user || ! $this->rosterReady()) {
            Notification::make()->title('Select class and subject first')->warning()->send();

            return;
        }

        $result = $service->markRemainingDone(
            $user,
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            (string) ($this->data['topic'] ?? ''),
            $this->checkDate(),
            $this->data['student_search'] ?? null,
            $this->assignmentId(),
        );

        if ($result['marked'] < 1) {
            Notification::make()->title('No unmarked students')->body('Everyone already has a mark for this date.')->warning()->send();

            return;
        }

        Notification::make()
            ->title('Marked remaining Done')
            ->body($result['marked'].' student(s) marked Done. No WhatsApp sent.')
            ->success()
            ->send();
    }

    public function resendWhatsApp(int $checkId, HomeworkCheckService $service): void
    {
        $user = Auth::user();

        if (! $user) {
            return;
        }

        try {
            $result = $service->resendWhatsApp($user, $checkId);
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not resend.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        if ($result['queued']) {
            Notification::make()
                ->title('WhatsApp resent')
                ->body($result['message'])
                ->success()
                ->send();

            return;
        }

        Notification::make()
            ->title('Resend failed')
            ->body($result['message'])
            ->warning()
            ->send();
    }

    protected function markOne(HomeworkCheckService $service, int $studentId, HomeworkCheckStatus $status): void
    {
        $user = Auth::user();

        if (! $user || ! $this->rosterReady()) {
            Notification::make()->title('Select class and subject first')->warning()->send();

            return;
        }

        try {
            $result = $service->mark(
                $user,
                (int) $this->data['batch_id'],
                $studentId,
                (int) $this->data['course_subject_id'],
                (string) ($this->data['topic'] ?? ''),
                $status,
                $this->checkDate(),
                $this->assignmentId(),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()
            ->title($status === HomeworkCheckStatus::Done ? 'Marked Done' : 'Marked Not Done')
            ->body($result['whatsapp']['message'] ?? '')
            ->success()
            ->send();
    }

    protected function rosterReady(): bool
    {
        return filled($this->data['batch_id'] ?? null)
            && filled($this->data['course_subject_id'] ?? null)
            && filled($this->data['check_date'] ?? null);
    }

    protected function checkDate(): ?string
    {
        $value = $this->data['check_date'] ?? null;

        if (! filled($value)) {
            return now()->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    protected function assignmentId(): ?int
    {
        $value = (int) ($this->data['homework_assignment_id'] ?? 0);

        return $value > 0 ? $value : null;
    }

    protected function checkDateLabel(): string
    {
        return Carbon::parse($this->checkDate())->format('d M Y');
    }

    /**
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, mobile: ?string, check_id: ?int, last_status: ?string, last_notify: ?string, can_resend: bool}>
     */
    protected function rosterStudents(): \Illuminate\Support\Collection
    {
        if (! $this->rosterReady()) {
            return collect();
        }

        return app(HomeworkCheckService::class)->rosterForBatch(
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            $this->data['student_search'] ?? null,
            $this->checkDate(),
        );
    }
}

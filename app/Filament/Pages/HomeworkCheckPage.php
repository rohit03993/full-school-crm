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

    public bool $confirmNotDoneOpen = false;

    public function getSubheading(): ?string
    {
        return 'Subject fills automatically when you teach only one. Select students who did not finish, Submit, then confirm WhatsApp count.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'batch_id' => null,
            'course_subject_id' => null,
            'check_date' => now()->toDateString(),
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
                ->description('Choose class and date. Subject is auto-selected when you are assigned to only one subject for that class.')
                ->schema([
                    Select::make('batch_id')
                        ->label('Class')
                        ->options(fn (): array => $user ? $service->batchOptionsFor($user) : [])
                        ->searchable()
                        ->required()
                        ->native(false)
                        ->live()
                        ->afterStateUpdated(function () use ($service, $user): void {
                            $this->selectedStudentIds = [];
                            $this->confirmNotDoneOpen = false;
                            $this->data['course_subject_id'] = null;
                            $this->autoSelectSubject($service, $user);
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
                        ->helperText(fn (): ?string => $this->subjectHelperText($service, $user))
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null))
                        ->afterStateUpdated(function (): void {
                            $this->selectedStudentIds = [];
                            $this->confirmNotDoneOpen = false;
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
                            $this->confirmNotDoneOpen = false;
                        }),
                    Textarea::make('topic')
                        ->label('Homework topic (optional)')
                        ->helperText('Included in the WhatsApp as {{4}}. Defaults to “Today\'s homework”.')
                        ->placeholder('e.g. Chapter 5 – Complete Questions 1 to 10')
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
                    $selected = $this->selectedStudentsPayload($students);

                    return [
                        'rosterReady' => $this->rosterReady(),
                        'students' => $students,
                        'selectedStudentIds' => $this->selectedStudentIds,
                        'checkDateLabel' => $this->checkDateLabel(),
                        'subjectLabel' => $this->subjectLabel(),
                        'summary' => $summary,
                        'unmarkedCount' => $summary['unmarked'],
                        'confirmNotDoneOpen' => $this->confirmNotDoneOpen,
                        'selectedCount' => $selected['count'],
                        'selectedWithMobile' => $selected['with_mobile'],
                        'selectedWithoutMobile' => $selected['without_mobile'],
                        'otherSubjectsToday' => $this->otherSubjectsToday(),
                        'recent' => filled($this->data['batch_id'] ?? null)
                            ? app(HomeworkCheckService::class)->recentForBatch(
                                (int) $this->data['batch_id'],
                                20,
                                $this->checkDate(),
                            )
                            : collect(),
                    ];
                }),
        ]);
    }

    public function updatedDataBatchId(mixed $value): void
    {
        $this->selectedStudentIds = [];
        $this->confirmNotDoneOpen = false;
        $this->data['course_subject_id'] = null;
        $this->autoSelectSubject(app(HomeworkCheckService::class), Auth::user());
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

    public function requestMarkSelectedNotDone(): void
    {
        if (! $this->rosterReady()) {
            Notification::make()->title('Select class, subject and date first')->warning()->send();

            return;
        }

        $ids = $this->normalizedSelectedIds();

        if ($ids === []) {
            Notification::make()->title('Select at least one student')->warning()->send();

            return;
        }

        $this->confirmNotDoneOpen = true;
    }

    public function cancelMarkSelectedNotDone(): void
    {
        $this->confirmNotDoneOpen = false;
    }

    public function confirmMarkSelectedNotDone(HomeworkCheckService $service): void
    {
        $user = Auth::user();
        $ids = $this->normalizedSelectedIds();

        if (! $user || ! $this->rosterReady() || $ids === []) {
            $this->confirmNotDoneOpen = false;
            Notification::make()->title('Select class, subject and students first')->warning()->send();

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
            null,
        );

        $this->selectedStudentIds = [];
        $this->confirmNotDoneOpen = false;

        Notification::make()
            ->title('Submitted Not Done')
            ->body(
                $result['marked'].' student(s) for '.$this->subjectLabel().'. '
                .'WhatsApp queued: '.$result['whatsappQueued']
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
            Notification::make()->title('Select class, subject and date first')->warning()->send();

            return;
        }

        $result = $service->markRemainingDone(
            $user,
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            (string) ($this->data['topic'] ?? ''),
            $this->checkDate(),
            $this->data['student_search'] ?? null,
            null,
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

    public function markStudentDone(int $studentId, HomeworkCheckService $service): void
    {
        $this->markOne($service, $studentId, HomeworkCheckStatus::Done);
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
            Notification::make()->title('Select class, subject and date first')->warning()->send();

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
                null,
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        Notification::make()
            ->title('Marked Done')
            ->body($result['whatsapp']['message'] ?? 'Saved. No WhatsApp sent.')
            ->success()
            ->send();
    }

    protected function autoSelectSubject(HomeworkCheckService $service, mixed $user): void
    {
        if (! $user || ! filled($this->data['batch_id'] ?? null)) {
            return;
        }

        $options = $service->subjectOptionsForBatch($user, (int) $this->data['batch_id']);

        if (count($options) === 1) {
            $this->data['course_subject_id'] = (int) array_key_first($options);
        }
    }

    protected function subjectHelperText(HomeworkCheckService $service, mixed $user): ?string
    {
        if (! $user || ! filled($this->data['batch_id'] ?? null)) {
            return null;
        }

        $options = $service->subjectOptionsForBatch($user, (int) $this->data['batch_id']);

        if (count($options) === 1) {
            return 'Auto-selected — you are assigned to only this subject for this class.';
        }

        if (count($options) > 1) {
            return 'Class subjects load automatically. Pick which subject you are checking now (e.g. Physics), then select students.';
        }

        return 'No subjects found for this class.';
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

    protected function checkDateLabel(): string
    {
        return Carbon::parse($this->checkDate())->format('d M Y');
    }

    protected function subjectLabel(): string
    {
        $user = Auth::user();
        $batchId = (int) ($this->data['batch_id'] ?? 0);
        $subjectId = (int) ($this->data['course_subject_id'] ?? 0);

        if (! $user || $batchId < 1 || $subjectId < 1) {
            return 'Subject';
        }

        $options = app(HomeworkCheckService::class)->subjectOptionsForBatch($user, $batchId);

        return (string) ($options[$subjectId] ?? $options[(string) $subjectId] ?? 'Subject');
    }

    /**
     * @return list<int>
     */
    protected function normalizedSelectedIds(): array
    {
        return collect($this->selectedStudentIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Support\Collection<int, array{id: int, mobile: ?string}>  $students
     * @return array{count: int, with_mobile: int, without_mobile: int}
     */
    protected function selectedStudentsPayload(\Illuminate\Support\Collection $students): array
    {
        $ids = $this->normalizedSelectedIds();
        $selected = $students->whereIn('id', $ids);
        $withMobile = $selected->filter(fn (array $row): bool => filled($row['mobile'] ?? null))->count();

        return [
            'count' => $selected->count(),
            'with_mobile' => $withMobile,
            'without_mobile' => max(0, $selected->count() - $withMobile),
        ];
    }

    /**
     * Other subjects checked today for this class (so Phy/Chem/Maths progress is visible).
     *
     * @return list<array{id: int, label: string, done: int, not_done: int, unmarked: int}>
     */
    protected function otherSubjectsToday(): array
    {
        $user = Auth::user();

        if (! $user || ! filled($this->data['batch_id'] ?? null)) {
            return [];
        }

        $grid = app(HomeworkCheckService::class)->multiSubjectGridForBatch(
            $user,
            (int) $this->data['batch_id'],
            $this->checkDate(),
            null,
        );

        $activeSubjectId = (int) ($this->data['course_subject_id'] ?? 0);
        $studentCount = count($grid['students']);
        $rows = [];

        foreach ($grid['subjects'] as $subject) {
            $done = 0;
            $notDone = 0;

            foreach ($grid['students'] as $student) {
                $status = $student['cells'][$subject['id']]['status'] ?? null;
                if ($status === HomeworkCheckStatus::Done->label()) {
                    $done++;
                } elseif ($status === HomeworkCheckStatus::NotDone->label()) {
                    $notDone++;
                }
            }

            $rows[] = [
                'id' => $subject['id'],
                'label' => $subject['label'],
                'is_active' => $subject['id'] === $activeSubjectId,
                'done' => $done,
                'not_done' => $notDone,
                'unmarked' => max(0, $studentCount - $done - $notDone),
            ];
        }

        return $rows;
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

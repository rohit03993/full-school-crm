<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Services\HomeworkCheckService;
use App\Services\HomeworkSubmissionService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\WhatsAppSendUi;
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

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::HomeworkManage;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::Homework;
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return false;
        }

        $user = Auth::user();

        if (! $user) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::HomeworkManage)
            || app(HomeworkSubmissionService::class)->canSubmit($user);
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

    public string $bulkStep = '';

    public string $bulkChoice = '';

    public ?int $singleNotDoneStudentId = null;

    public function getSubheading(): ?string
    {
        return 'Subject fills automatically when you teach only one. Mark a student only after homework was given for that day.';
    }

    public function mount(): void
    {
        $user = Auth::user();
        $service = app(HomeworkCheckService::class);
        $batchId = request()->integer('batch_id');
        $subjectId = request()->integer('course_subject_id');
        $requestedDate = request()->query('check_date');
        $date = $service->latestCheckDate();

        if (filled($requestedDate)) {
            $parsed = Carbon::parse((string) $requestedDate)->toDateString();

            if ($parsed <= now()->toDateString()) {
                $date = $parsed;
            }
        }

        if ($batchId > 0 && $user && $service->userCanAccessBatch($user, $batchId)) {
            $subjects = $service->subjectOptionsForBatch($user, $batchId);

            if ($subjectId > 0 && ! array_key_exists($subjectId, $subjects)) {
                $subjectId = 0;
            }

            if ($subjectId < 1 && count($subjects) === 1) {
                $subjectId = (int) array_key_first($subjects);
            }
        } else {
            $batchId = 0;
            $subjectId = 0;
        }

        $this->form->fill([
            'batch_id' => $batchId > 0 ? $batchId : null,
            'course_subject_id' => $subjectId > 0 ? $subjectId : null,
            'check_date' => $date,
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
                            $this->clearBulkSelection();
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
                            $this->clearBulkSelection();
                        }),
                    DatePicker::make('check_date')
                        ->label('Check date')
                        ->native(false)
                        ->required()
                        ->minDate(fn (): string => $service->earliestCheckDate())
                        ->maxDate(fn (): string => $service->latestCheckDate())
                        ->helperText('Last 7 days only. Homework given today can be checked from tomorrow.')
                        ->live()
                        ->visible(fn (): bool => filled($this->data['batch_id'] ?? null))
                        ->afterStateUpdated(function (): void {
                            $this->clearBulkSelection();
                        }),
                    Textarea::make('topic')
                        ->label('Homework topic (optional)')
                        ->helperText('Included in the WhatsApp as {{4}}. Defaults to “Today\'s homework”.')
                        ->placeholder('e.g. Chapter 5 – Complete Questions 1 to 10')
                        ->rows(2)
                        ->columnSpanFull()
                        ->visible(fn (): bool => $this->homeworkListOpen()),
                    TextInput::make('student_search')
                        ->label('Filter students')
                        ->placeholder('Type a name…')
                        ->live(debounce: 300)
                        ->visible(fn (): bool => $this->homeworkListOpen()),
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
                    $this->closeOpenStudentsIfNeeded();
                    $students = $this->rosterStudents();
                    $summary = app(HomeworkCheckService::class)->daySummaryFromRoster($students);
                    $selected = $this->selectedStudentsPayload($students);

                    $homeworkGiven = $this->homeworkListOpen();
                    $homeworkAwaitingApproval = $this->homeworkAwaitingApproval();
                    $showStudentMobile = CrmAccess::canViewStudentMobile(Auth::user());
                    $visibleStudents = $homeworkGiven ? $students : collect();

                    if (! $showStudentMobile) {
                        $visibleStudents = $visibleStudents->map(function (array $row): array {
                            $row['mobile'] = null;

                            return $row;
                        });
                    }

                    return [
                        'rosterReady' => $this->rosterReady(),
                        'checkDateBlocked' => $this->checkDateBlockedMessage(),
                        'homeworkGiven' => $homeworkGiven,
                        'homeworkAwaitingApproval' => $homeworkAwaitingApproval,
                        'showStudentMobile' => $showStudentMobile,
                        'students' => $visibleStudents,
                        'selectedStudentIds' => $this->normalizedSelectedIds(),
                        'bulkStep' => $this->bulkStep,
                        'bulkChoice' => $this->bulkChoice,
                        'bulkSummary' => $this->bulkStep === 'confirm' ? $this->bulkSummary() : null,
                        'singleNotDone' => $this->singleNotDoneSummary(),
                        'checkDateLabel' => $this->checkDateLabel(),
                        'subjectLabel' => $this->subjectLabel(),
                        'summary' => $summary,
                        'unmarkedCount' => $summary['unmarked'],
                        'selectedCount' => count($this->normalizedSelectedIds()),
                        'selectedWithMobile' => $selected['with_mobile'],
                        'selectedWithoutMobile' => $selected['without_mobile'],
                        'otherSubjectsToday' => $this->otherSubjectsToday(),
                    ];
                }),
        ]);
    }

    public function updatedDataBatchId(mixed $value): void
    {
        $this->clearBulkSelection();
        $this->data['course_subject_id'] = null;
        $this->autoSelectSubject(app(HomeworkCheckService::class), Auth::user());
    }

    public function toggleStudent(int $studentId): void
    {
        $this->bulkStep = '';
        $this->bulkChoice = '';
        $this->singleNotDoneStudentId = null;
        $ids = $this->normalizedSelectedIds();

        if (in_array($studentId, $ids, true)) {
            $this->selectedStudentIds = array_values(array_filter(
                $ids,
                fn (int $id): bool => $id !== $studentId,
            ));

            return;
        }

        $ids[] = $studentId;
        $this->selectedStudentIds = $ids;
    }

    public function toggleSelectAll(): void
    {
        $this->bulkStep = '';
        $this->bulkChoice = '';
        $this->singleNotDoneStudentId = null;
        $visibleIds = $this->rosterStudents()->pluck('id')->map(fn ($id): int => (int) $id)->all();
        $selected = $this->normalizedSelectedIds();
        $allVisibleSelected = $visibleIds !== [] && count(array_diff($visibleIds, $selected)) === 0;

        if ($allVisibleSelected) {
            $this->selectedStudentIds = array_values(array_diff($selected, $visibleIds));

            return;
        }

        $this->selectedStudentIds = array_values(array_unique([...$selected, ...$visibleIds]));
    }

    public function openBulkAsk(): void
    {
        if ($this->normalizedSelectedIds() === []) {
            Notification::make()->title('Tick at least one student')->warning()->send();

            return;
        }

        $this->bulkChoice = '';
        $this->bulkStep = 'ask';
    }

    public function chooseBulk(string $choice): void
    {
        if (! in_array($choice, ['done', 'not_done'], true)) {
            return;
        }

        if ($this->normalizedSelectedIds() === []) {
            $this->bulkStep = '';

            return;
        }

        $this->bulkChoice = $choice;
        $this->bulkStep = 'confirm';
    }

    public function backToBulkAsk(): void
    {
        $this->bulkStep = 'ask';
    }

    public function cancelBulk(): void
    {
        $this->bulkStep = '';
        $this->bulkChoice = '';
    }

    public function confirmBulk(HomeworkCheckService $service): void
    {
        $user = Auth::user();

        if (! $user || ! $this->rosterReady() || ! in_array($this->bulkChoice, ['done', 'not_done'], true)) {
            Notification::make()->title('Choose the class, subject, and an answer first')->warning()->send();

            return;
        }

        try {
            $result = $service->applySelection(
                $user,
                (int) $this->data['batch_id'],
                (int) $this->data['course_subject_id'],
                $this->normalizedSelectedIds(),
                $this->bulkChoice,
                (string) ($this->data['topic'] ?? ''),
                $this->checkDate(),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->clearBulkSelection();

        $body = $result['done'].' marked Done. '.$result['not_done'].' marked Not done. WhatsApp queued: '.$result['whatsappQueued'].'.';

        if ($result['whatsappFailed'] > 0) {
            $body .= ' '.$result['whatsappFailed'].' did not get a new message (no mobile number, or the message was already shared).';
        }

        if ($result['errors'] !== []) {
            $body .= ' '.implode(' ', array_slice($result['errors'], 0, 2));
        }

        Notification::make()
            ->title('Class homework saved')
            ->body($body)
            ->success()
            ->send();
    }

    public function clearBulkSelection(): void
    {
        $this->selectedStudentIds = [];
        $this->bulkStep = '';
        $this->bulkChoice = '';
        $this->singleNotDoneStudentId = null;
    }

    /**
     * @return array{ticked: int, done: int, not_done: int, messages: int, no_mobile: int, already_shared: int}
     */
    protected function bulkSummary(): array
    {
        $class = $this->classRoster();
        $selectedIds = array_values(array_intersect(
            $this->normalizedSelectedIds(),
            $class->pluck('id')->map(fn ($id): int => (int) $id)->all(),
        ));
        $selected = $class->whereIn('id', $selectedIds)->values();
        $rest = $class->reject(fn (array $row): bool => in_array((int) $row['id'], $selectedIds, true))->values();
        $notDone = $this->bulkChoice === 'done' ? $rest : $selected;
        $done = $this->bulkChoice === 'done' ? $selected : $rest;
        $freshNotDone = $notDone->filter(fn (array $row): bool => ($row['status_key'] ?? null) !== 'not_done');

        return [
            'ticked' => $selected->count(),
            'done' => $done->count(),
            'not_done' => $notDone->count(),
            'messages' => $freshNotDone->filter(fn (array $row): bool => filled($row['mobile'] ?? null))->count(),
            'no_mobile' => $freshNotDone->filter(fn (array $row): bool => blank($row['mobile'] ?? null))->count(),
            'already_shared' => $notDone->filter(fn (array $row): bool => ($row['status_key'] ?? null) === 'not_done')->count(),
        ];
    }

    protected function classRoster(): \Illuminate\Support\Collection
    {
        if (! $this->rosterReady()) {
            return collect();
        }

        return app(HomeworkCheckService::class)->rosterForBatch(
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            null,
            $this->checkDate(),
        );
    }

    public function markStudentDone(int $studentId, HomeworkCheckService $service): void
    {
        $this->singleNotDoneStudentId = null;
        $this->markOne($service, $studentId, HomeworkCheckStatus::Done);
    }

    public function askSingleNotDone(int $studentId): void
    {
        if (! $this->rosterReady()) {
            Notification::make()->title('Select class, subject and date first')->warning()->send();

            return;
        }

        $inClass = $this->classRoster()->contains(
            fn (array $row): bool => (int) $row['id'] === $studentId,
        );

        if (! $inClass) {
            Notification::make()->title('Student is not in this class')->warning()->send();

            return;
        }

        $this->bulkStep = '';
        $this->bulkChoice = '';
        $this->singleNotDoneStudentId = $studentId;
    }

    public function cancelSingleNotDone(): void
    {
        $this->singleNotDoneStudentId = null;
    }

    public function confirmSingleNotDone(HomeworkCheckService $service): void
    {
        $user = Auth::user();
        $studentId = (int) $this->singleNotDoneStudentId;

        if (! $user || ! $this->rosterReady() || $studentId < 1) {
            Notification::make()->title('Choose the class, subject, and student first')->warning()->send();

            return;
        }

        try {
            $result = $service->markNotDoneAndCloseOpen(
                $user,
                (int) $this->data['batch_id'],
                $studentId,
                (int) $this->data['course_subject_id'],
                (string) ($this->data['topic'] ?? ''),
                $this->checkDate(),
            );
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first() ?? 'Could not save.';
            Notification::make()->title((string) $message)->warning()->send();

            return;
        }

        $this->singleNotDoneStudentId = null;

        $body = $result['whatsappQueued']
            ? 'Homework not done. Message shared with parents.'
            : ($result['whatsappMessage'] !== ''
                ? $result['whatsappMessage']
                : 'Homework not done. Message was not shared.');

        if ($result['done'] > 0) {
            $body .= ' '.$result['done'].' open '.($result['done'] === 1 ? 'student' : 'students').' marked Done. No message to them.';
        }

        Notification::make()
            ->title('Marked Not done')
            ->body($body)
            ->success()
            ->send();
    }

    /**
     * @return array{name: string, open: int, will_message: bool, already_shared: bool, no_mobile: bool}|null
     */
    protected function singleNotDoneSummary(): ?array
    {
        $studentId = (int) $this->singleNotDoneStudentId;

        if ($studentId < 1 || ! $this->rosterReady()) {
            return null;
        }

        $class = $this->classRoster();
        $student = $class->first(fn (array $row): bool => (int) $row['id'] === $studentId);

        if (! is_array($student)) {
            return null;
        }

        $alreadyShared = ($student['status_key'] ?? null) === 'not_done';

        return [
            'name' => (string) $student['name'],
            'open' => $class->filter(
                fn (array $row): bool => blank($row['last_status']) && (int) $row['id'] !== $studentId,
            )->count(),
            'will_message' => ! $alreadyShared && filled($student['mobile'] ?? null),
            'already_shared' => $alreadyShared,
            'no_mobile' => ! $alreadyShared && blank($student['mobile'] ?? null),
        ];
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
                ->title('WhatsApp queued')
                ->body('Opening send progress. Do not click Resend again.')
                ->success()
                ->send();

            if ($url = WhatsAppSendUi::campaignViewUrl($result['campaign_id'] ?? null)) {
                $this->redirect($url);
            }

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

        $markedNotDone = $status === HomeworkCheckStatus::NotDone;

        Notification::make()
            ->title($markedNotDone ? 'Marked Not done' : 'Marked Done')
            ->body($markedNotDone
                ? (($result['whatsapp']['queued'] ?? false)
                    ? 'Homework not done. Message shared with parents.'
                    : (string) ($result['whatsapp']['message'] ?? 'Homework not done. Message was not shared.'))
                : 'Status changed to Done. No message sent to parents.')
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

    protected function closeOpenStudentsIfNeeded(): void
    {
        $user = Auth::user();

        if (! $user || $this->checkDateBlockedMessage() !== null || ! $this->homeworkListOpen()) {
            return;
        }

        $marked = app(HomeworkCheckService::class)->closeOpenStudentsWhenAnyNotDone(
            $user,
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            (string) ($this->data['topic'] ?? ''),
            $this->checkDate(),
        );

        if ($marked < 1) {
            return;
        }

        Notification::make()
            ->title($marked.' open '.($marked === 1 ? 'student' : 'students').' marked Done')
            ->body('No message was sent to them.')
            ->success()
            ->send();
    }

    protected function checkDateBlockedMessage(): ?string
    {
        if (! $this->rosterReady()) {
            return null;
        }

        $service = app(HomeworkCheckService::class);
        $date = (string) $this->checkDate();

        if ($date > $service->latestCheckDate()) {
            return "Today's homework cannot be checked today. It can be checked from tomorrow.";
        }

        if ($date < $service->earliestCheckDate()) {
            return 'You can check only the last 7 days of homework.';
        }

        return null;
    }

    protected function homeworkListOpen(): bool
    {
        if (! $this->rosterReady() || $this->checkDateBlockedMessage() !== null) {
            return false;
        }

        return app(HomeworkCheckService::class)->homeworkReadyToMark(
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            $this->checkDate(),
        );
    }

    protected function homeworkAwaitingApproval(): bool
    {
        if (! $this->rosterReady() || $this->homeworkListOpen()) {
            return false;
        }

        return app(HomeworkCheckService::class)->homeworkWasGiven(
            (int) $this->data['batch_id'],
            (int) $this->data['course_subject_id'],
            $this->checkDate(),
        );
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
            return app(HomeworkCheckService::class)->latestCheckDate();
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
     * @return \Illuminate\Support\Collection<int, array{id: int, name: string, mobile: ?string, check_id: ?int, last_status: ?string, last_notify: ?string, can_resend: bool, link_tracked: bool, link_opened: bool, link_opened_at: ?string}>
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

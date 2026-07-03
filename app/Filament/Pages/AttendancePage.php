<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\FinishesAttendanceSave;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enrollment;
use App\Models\Student;
use App\Services\AttendanceService;
use App\Services\Punch\LivePunchDashboardService;
use App\Services\Punch\ManualBatchAttendanceService;
use App\Services\Punch\PunchAttendanceProcessor;
use App\Services\Punch\PunchBatchRosterService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AttendancePage extends Page
{
    use FinishesAttendanceSave;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Attendance';

    protected static ?int $navigationSort = 39;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var 'live'|'manual' */
    public string $viewMode = 'live';

    /**
     * @var array{
     *     date: ?string,
     *     batch_id: ?int,
     *     roll: ?string,
     *     name: ?string,
     *     state: ?string,
     * }
     */
    public array $filters = [
        'date' => null,
        'batch_id' => null,
        'roll' => null,
        'name' => null,
        'state' => null,
    ];

    /** @var array<string, mixed> */
    public array $dashboard = [
        'stats' => ['total' => 0, 'inside' => 0, 'out' => 0],
        'rows' => [],
    ];

    public bool $punchTableReady = false;

    public ?string $lastRefreshedAt = null;

    public string $quickSearch = '';

    public ?string $highlightRoll = null;

    /** @var array<string, mixed> */
    public array $batchRoster = [
        'enabled' => false,
        'batch_name' => null,
        'present' => [],
        'absent' => [],
        'counts' => ['total' => 0, 'present' => 0, 'absent' => 0],
    ];

    /**
     * @var array<int, string>
     */
    public array $marks = [];

    public bool $rosterLoaded = false;

    /**
     * @var Collection<int, BatchStudent>
     */
    public Collection $roster;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::AttendanceMark);
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    public function mount(LivePunchDashboardService $dashboard): void
    {
        $this->filters['date'] = now()->toDateString();
        $this->roster = new Collection;

        if (request()->query('mode') === 'manual') {
            $this->viewMode = 'manual';
        }

        if ($batchId = request()->integer('batch_id')) {
            $this->filters['batch_id'] = $batchId;
        }

        if ($this->viewMode === 'manual') {
            $this->loadRoster();
        } else {
            $this->refreshDashboard($dashboard);
        }
    }

    public function switchMode(string $mode): void
    {
        $this->viewMode = $mode === 'manual' ? 'manual' : 'live';

        if ($this->viewMode === 'manual') {
            $this->loadRoster();
        } else {
            $this->refreshDashboard();
        }
    }

    public function refreshDashboard(?LivePunchDashboardService $dashboard = null, ?PunchAttendanceProcessor $processor = null): void
    {
        $dashboard ??= app(LivePunchDashboardService::class);

        if ($processor) {
            $processor->processPending();
        } elseif ($dashboard->punchTableReady()) {
            app(PunchAttendanceProcessor::class)->processPending();
        }

        $this->punchTableReady = $dashboard->punchTableReady();
        $this->dashboard = $dashboard->dashboardForDate(
            $this->filters['date'] ?? now()->toDateString(),
            filled($this->filters['batch_id'] ?? null) ? (int) $this->filters['batch_id'] : null,
            filled($this->filters['roll'] ?? null) ? (string) $this->filters['roll'] : null,
            filled($this->filters['name'] ?? null) ? (string) $this->filters['name'] : null,
            filled($this->filters['state'] ?? null) ? (string) $this->filters['state'] : null,
        );

        $this->lastRefreshedAt = now()->format('H:i:s');

        $batchId = filled($this->filters['batch_id'] ?? null) ? (int) $this->filters['batch_id'] : null;

        $this->batchRoster = $batchId
            ? app(PunchBatchRosterService::class)->rosterForBatch($batchId, $this->filters['date'] ?? now()->toDateString())
            : [
                'enabled' => false,
                'batch_name' => null,
                'present' => [],
                'absent' => [],
                'counts' => ['total' => 0, 'present' => 0, 'absent' => 0],
            ];
    }

    public function searchAndFocus(PunchBatchRosterService $roster): void
    {
        $term = trim($this->quickSearch);

        if ($term === '') {
            $this->highlightRoll = null;

            return;
        }

        $batchId = filled($this->filters['batch_id'] ?? null) ? (int) $this->filters['batch_id'] : null;
        $result = $roster->findByQuickSearch(
            $term,
            $this->filters['date'] ?? now()->toDateString(),
            $batchId,
        );

        if ($result['roll']) {
            $this->highlightRoll = $result['roll'];
            $this->filters['roll'] = null;
            $this->filters['name'] = null;
            $this->filters['state'] = null;
            $this->refreshDashboard();

            return;
        }

        Notification::make()
            ->title('No match found')
            ->body('Try roll number, 10-digit mobile, or part of the student name.')
            ->warning()
            ->send();
    }

    public function clearQuickSearch(): void
    {
        $this->quickSearch = '';
        $this->highlightRoll = null;
        $this->filters['roll'] = null;
        $this->filters['name'] = null;
        $this->refreshDashboard();
    }

    public function filterByState(?string $state = null): void
    {
        $normalized = $state === '' ? null : $state;
        $current = filled($this->filters['state'] ?? null) ? (string) $this->filters['state'] : null;

        $this->filters['state'] = ($current === $normalized) ? null : $normalized;
        $this->refreshDashboard();
    }

    public function updatedFilters(LivePunchDashboardService $dashboard): void
    {
        if ($this->viewMode === 'manual') {
            $this->rosterLoaded = false;
            $this->marks = [];
            $this->loadRoster();

            return;
        }

        $this->refreshDashboard($dashboard);
    }

    public function loadRoster(): void
    {
        $batchId = $this->filters['batch_id'] ?? null;
        $date = $this->filters['date'] ?? null;

        if (! $batchId || ! $date) {
            $this->roster = new Collection;
            $this->marks = [];
            $this->rosterLoaded = false;

            return;
        }

        $this->rosterLoaded = true;
        $batch = Batch::query()->findOrFail($batchId);

        $this->roster = BatchStudent::query()
            ->where('batch_students.batch_id', $batch->id)
            ->where('batch_students.is_active', true)
            ->join('students', 'students.id', '=', 'batch_students.student_id')
            ->orderBy('students.name')
            ->select('batch_students.*')
            ->with('student')
            ->get();

        $existing = app(AttendanceService::class)->marksForBatchDate($batch, $date);

        $this->marks = [];

        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = $existing[$row->student_id]
                ?? AttendanceStatus::Absent->value;
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = AttendanceStatus::Present->value;
        }
    }

    public function saveAttendance(ManualBatchAttendanceService $manualBatch, array $marks = []): void
    {
        if ($marks !== []) {
            $this->marks = $this->normalizeStatusMarksFromClient($marks);
        }

        $batchId = $this->filters['batch_id'] ?? null;
        $date = $this->filters['date'] ?? null;

        if (! $batchId || ! $date) {
            Notification::make()
                ->title('Select batch and date')
                ->warning()
                ->send();

            return;
        }

        $batch = Batch::query()->findOrFail($batchId);
        $stats = $manualBatch->save($batch, $date, $this->marks, Auth::user());

        $body = "{$stats['saved']} record(s) saved for {$batch->name} · ".$this->formatAttendanceDate($date);

        if ($stats['in_punches'] > 0) {
            $body .= " · {$stats['in_punches']} check-in (IN).";
        }

        if ($stats['whatsapp_queued'] > 0) {
            $body .= " · {$stats['whatsapp_queued']} parent WhatsApp queued.";
        }

        if ($stats['whatsapp_skipped'] > 0) {
            $body .= " · {$stats['whatsapp_skipped']} WhatsApp not sent (check Settings, mobile, or template).";
        }

        if ($stats['no_roll'] > 0) {
            $body .= " · {$stats['no_roll']} marked present without roll — add enrollment number for IN/WhatsApp.";
        }

        Notification::make()
            ->title('Attendance saved')
            ->body($body)
            ->success()
            ->duration(10000)
            ->send();

        $this->loadRoster();
    }

    public function markManualInForStudent(
        ManualBatchAttendanceService $manualBatch,
        int $studentId,
    ): void {
        $date = $this->filters['date'] ?? now()->toDateString();
        $student = Student::query()->find($studentId);

        if (! $student) {
            Notification::make()->title('Student not found')->danger()->send();

            return;
        }

        $result = $manualBatch->manualIn($student, $date, Auth::user());

        if (! $result['ok']) {
            Notification::make()
                ->title('Cannot check in')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $this->marks[$studentId] = AttendanceStatus::Present->value;
        $this->loadRoster();
        $this->notifyManualPunchResult('Check-in (IN) saved', $student->name, $result);
    }

    public function markManualOutForStudent(
        ManualBatchAttendanceService $manualBatch,
        int $studentId,
    ): void {
        $date = $this->filters['date'] ?? now()->toDateString();
        $student = Student::query()->find($studentId);

        if (! $student) {
            Notification::make()->title('Student not found')->danger()->send();

            return;
        }

        $result = $manualBatch->manualOut($student, $date, Auth::user());

        if (! $result['ok']) {
            Notification::make()
                ->title('Cannot check out')
                ->body($result['message'])
                ->warning()
                ->send();

            return;
        }

        $this->loadRoster();
        $this->notifyManualPunchResult('Check-out (OUT) saved', $student->name, $result);
    }

    /**
     * @param  array{ok: bool, message: string, whatsapp: array{queued: bool, message: string}|null}  $result
     */
    private function notifyManualPunchResult(string $title, string $studentName, array $result): void
    {
        $body = "{$studentName}: {$result['message']}";

        if ($whatsapp = $result['whatsapp'] ?? null) {
            $body .= ' '.$whatsapp['message'];
        }

        $notification = Notification::make()
            ->title($title)
            ->body($body)
            ->duration(10000);

        if (($result['whatsapp']['queued'] ?? false) === true) {
            $notification->success()->send();

            return;
        }

        $notification->warning()->send();
    }

    /**
     * @return list<array{date: string, label: string, present: int, absent: int, leave: int, total: int}>
     */
    public function markedDateSummaries(): array
    {
        $batchId = $this->filters['batch_id'] ?? null;

        if (! $batchId) {
            return [];
        }

        $batch = Batch::query()->find($batchId);

        if (! $batch) {
            return [];
        }

        return app(AttendanceService::class)->markedDateSummariesForBatch($batch);
    }

    public function openMarkedDate(string $date): void
    {
        $this->filters['date'] = $date;
        $this->loadRoster();
    }

    public function markManualPunch(
        PunchAttendanceProcessor $processor,
        LivePunchDashboardService $dashboard,
        string $roll,
        string $state,
        ?string $time = null,
    ): void {
        $roll = strtoupper(trim($roll));
        $date = $this->filters['date'] ?? now()->toDateString();
        $time = $time ?: now()->format('H:i:s');

        $enrollment = Enrollment::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(enrollment_number) = ?', [$roll])
            ->with('student')
            ->first();

        if (! $enrollment?->student) {
            Notification::make()
                ->title('Student not found')
                ->body("No active enrollment for roll {$roll}.")
                ->danger()
                ->send();

            return;
        }

        $result = $processor->handleManualPunch(
            $enrollment->student,
            $roll,
            $date,
            $time,
            $state,
            Auth::user(),
        );

        if ($this->viewMode === 'live') {
            $this->refreshDashboard($dashboard);
        } else {
            $this->loadRoster();
        }

        $this->notifyManualPunchResult(
            'Manual punch saved',
            $enrollment->student->name,
            [
                'ok' => true,
                'message' => "{$state} recorded at {$time}.",
                'whatsapp' => $result['whatsapp'],
            ],
        );
    }

    protected function formatAttendanceDate(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    public function liveFiltersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns([
                'default' => 1,
                'sm' => 2,
                'lg' => 3,
                'xl' => 5,
            ])
            ->components([
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->maxDate(now())
                    ->native(false),
                Select::make('batch_id')
                    ->label('Batch')
                    ->options(fn (LivePunchDashboardService $dashboard): array => collect($dashboard->activeBatchOptions())
                        ->mapWithKeys(fn (array $batch): array => [$batch['id'] => $batch['name']])
                        ->all())
                    ->searchable()
                    ->placeholder('All batches')
                    ->nullable()
                    ->native(false),
                TextInput::make('roll')
                    ->label('Roll no.')
                    ->placeholder('Filter roll'),
                TextInput::make('name')
                    ->label('Student name')
                    ->placeholder('Filter name'),
                Select::make('state')
                    ->label('Status')
                    ->options([
                        '' => 'All statuses',
                        'IN' => 'Inside',
                        'OUT' => 'Checked out',
                    ])
                    ->native(false),
            ]);
    }

    public function manualFiltersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->components([
                Select::make('batch_id')
                    ->label('Batch')
                    ->options(fn (): array => Batch::query()
                        ->where('status', BatchStatus::Active)
                        ->with('course')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Batch $batch): array => [
                            $batch->id => "{$batch->name} · {$batch->course?->name}",
                        ])
                        ->all())
                    ->searchable()
                    ->required()
                    ->native(false),
                DatePicker::make('date')
                    ->label('Date')
                    ->required()
                    ->native(false),
            ]);
    }

    public function getLiveFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('liveFiltersForm')])
            ->id('attendanceLiveFilters');
    }

    public function getManualFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('manualFiltersForm')])
            ->id('attendanceManualFilters')
            ->livewireSubmitHandler('loadRoster')
            ->footer([
                Actions::make([
                    \Filament\Actions\Action::make('loadRoster')
                        ->label('Load Students')
                        ->icon(Heroicon::OutlinedArrowPath)
                        ->action('loadRoster'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.attendance-mode-toggle')
                ->viewData(fn (): array => [
                    'viewMode' => $this->viewMode,
                    'lastRefreshedAt' => $this->lastRefreshedAt,
                    'punchTableReady' => $this->punchTableReady,
                ]),
            Section::make('Filters')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->schema([
                    $this->getLiveFiltersFormComponent(),
                ])
                ->compact()
                ->visible(fn (): bool => $this->viewMode === 'live'),
            View::make('filament.pages.partials.live-punch-dashboard')
                ->viewData(fn (): array => [
                    'punchTableReady' => $this->punchTableReady,
                    'dashboard' => $this->dashboard,
                    'batchRoster' => $this->batchRoster,
                    'selectedDate' => $this->filters['date'] ?? now()->toDateString(),
                    'selectedDateLabel' => Carbon::parse($this->filters['date'] ?? now())->format('d M Y'),
                    'lastRefreshedAt' => $this->lastRefreshedAt,
                    'activeStateFilter' => filled($this->filters['state'] ?? null) ? (string) $this->filters['state'] : null,
                    'highlightRoll' => $this->highlightRoll,
                    'quickSearch' => $this->quickSearch,
                ])
                ->visible(fn (): bool => $this->viewMode === 'live'),
            Section::make('Batch & date')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    $this->getManualFiltersFormComponent(),
                ])
                ->compact()
                ->visible(fn (): bool => $this->viewMode === 'manual'),
            View::make('filament.pages.partials.batch-attendance-history')
                ->viewData(fn (): array => [
                    'summaries' => $this->markedDateSummaries(),
                    'selectedDate' => $this->filters['date'] ?? null,
                ])
                ->visible(fn (): bool => $this->viewMode === 'manual' && filled($this->filters['batch_id'])),
            View::make('filament.pages.partials.batch-attendance-roster')
                ->viewData(fn (): array => [
                    'rosterLoaded' => $this->rosterLoaded,
                    'roster' => $this->roster,
                    'marks' => $this->marks,
                    'statuses' => AttendanceStatus::cases(),
                ])
                ->visible(fn (): bool => $this->viewMode === 'manual'),
        ]);
    }
}

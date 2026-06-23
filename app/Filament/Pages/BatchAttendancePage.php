<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FinishesAttendanceSave;
use App\Enums\AttendanceStatus;
use App\Enums\CrmPermission;
use App\Enums\BatchStatus;
use App\Support\CrmAccess;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Services\AttendanceService;
use App\Services\AttendanceWhatsAppService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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

class BatchAttendancePage extends Page
{
    use FinishesAttendanceSave;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Batch Attendance';

    protected static ?int $navigationSort = 40;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::AttendanceMark);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('attendance.batch');
    }

    /**
     * @var array{batch_id: ?int, attendance_date: ?string}
     */
    public array $filters = [
        'batch_id' => null,
        'attendance_date' => null,
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

    public function mount(): void
    {
        $this->filters['attendance_date'] = now()->toDateString();
        $this->roster = new Collection;
    }

    public function updatedFilters(): void
    {
        $this->rosterLoaded = false;
        $this->marks = [];
        $this->loadRoster();
    }

    public function loadRoster(): void
    {
        $batchId = $this->filters['batch_id'] ?? null;
        $date = $this->filters['attendance_date'] ?? null;

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
                ?? AttendanceStatus::Present->value;
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = AttendanceStatus::Present->value;
        }
    }

    public function saveAttendance(AttendanceService $attendance, AttendanceWhatsAppService $attendanceWhatsApp, array $marks = []): void
    {
        if ($marks !== []) {
            $this->marks = $this->normalizeStatusMarksFromClient($marks);
        }

        $batchId = $this->filters['batch_id'] ?? null;
        $date = $this->filters['attendance_date'] ?? null;

        if (! $batchId || ! $date) {
            Notification::make()
                ->title('Select batch and date')
                ->warning()
                ->send();

            return;
        }

        $batch = Batch::query()->findOrFail($batchId);
        $saved = $attendance->saveBatchAttendance($batch, $date, $this->marks, Auth::user());
        $queued = $attendanceWhatsApp->maybeQueueAfterBatchAttendance($batch, $date, $this->marks, Auth::user());

        $body = "{$saved} record(s) saved for {$batch->name} · ".$this->formatAttendanceDate($date);

        if ($queued !== null) {
            $body .= " WhatsApp queued for {$queued} present student(s).";
        }

        $this->finishAttendanceSave('Attendance saved', $body);
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
        $this->filters['attendance_date'] = $date;
        $this->loadRoster();
    }

    protected function formatAttendanceDate(string $date): string
    {
        return Carbon::parse($date)->format('d M Y');
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
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
                DatePicker::make('attendance_date')
                    ->label('Date')
                    ->required()
                    ->native(false),
            ]);
    }

    public function getFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('filtersForm')])
            ->id('batchAttendanceFilters')
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
            Section::make('Select Batch & Date')
                ->description('Pick the batch and class date, then mark Present (P), Absent (A), or Leave (L).')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->schema([
                    $this->getFiltersFormComponent(),
                ])
                ->compact(),
            View::make('filament.pages.partials.batch-attendance-history')
                ->viewData(fn (): array => [
                    'summaries' => $this->markedDateSummaries(),
                    'selectedDate' => $this->filters['attendance_date'] ?? null,
                ])
                ->visible(fn (): bool => filled($this->filters['batch_id'])),
            View::make('filament.pages.partials.batch-attendance-roster')
                ->viewData(fn (): array => [
                    'rosterLoaded' => $this->rosterLoaded,
                    'roster' => $this->roster,
                    'marks' => $this->marks,
                    'statuses' => AttendanceStatus::cases(),
                ]),
        ]);
    }
}

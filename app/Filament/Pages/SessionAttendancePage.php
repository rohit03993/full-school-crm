<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\FinishesAttendanceSave;
use App\Enums\CrmPermission;
use App\Enums\BatchStatus;
use App\Support\CrmAccess;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Services\ActivityAttendanceService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
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
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class SessionAttendancePage extends Page
{
    use FinishesAttendanceSave;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Workshops & Events';

    protected static ?string $title = 'Workshop & Event Attendance';

    protected static ?int $navigationSort = 42;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::AttendanceWorkshops);
    }

    /**
     * @var array{
     *     activity_type_id: ?int,
     *     batch_id: ?int,
     *     session_date: ?string,
     *     session_title: ?string
     * }
     */
    public array $filters = [
        'activity_type_id' => null,
        'batch_id' => null,
        'session_date' => null,
        'session_title' => null,
    ];

    /**
     * @var array<int, bool>
     */
    public array $marks = [];

    public bool $rosterLoaded = false;

    public ?int $sessionId = null;

    /**
     * @var Collection<int, BatchStudent>
     */
    public Collection $roster;

    public function mount(): void
    {
        $this->filters['session_date'] = now()->toDateString();
        $this->roster = new Collection;

        $typeId = request()->query('activity_type_id');
        $batchId = request()->query('batch_id');

        if (filled($typeId)) {
            $this->filters['activity_type_id'] = (int) $typeId;
        }

        if (filled($batchId)) {
            $this->filters['batch_id'] = (int) $batchId;
        }

        $title = request()->query('session_title');

        if (filled($title)) {
            $this->filters['session_title'] = (string) $title;
        }

        $date = request()->query('session_date');

        if (filled($date)) {
            $this->filters['session_date'] = (string) $date;
        }
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('attendance.session');
    }

    public function updatedFilters(): void
    {
        $this->rosterLoaded = false;
        $this->sessionId = null;
        $this->marks = [];
    }

    public function loadRoster(ActivityAttendanceService $attendance): void
    {
        if (! $this->filtersReady()) {
            Notification::make()
                ->title('Complete all fields')
                ->body('Select type, batch, date, and session name, then load students.')
                ->warning()
                ->send();

            $this->rosterLoaded = false;
            $this->roster = new Collection;
            $this->marks = [];

            return;
        }

        $batch = Batch::query()->findOrFail((int) $this->filters['batch_id']);
        $this->rosterLoaded = true;

        $this->roster = BatchStudent::query()
            ->where('batch_students.batch_id', $batch->id)
            ->where('batch_students.is_active', true)
            ->join('students', 'students.id', '=', 'batch_students.student_id')
            ->orderBy('students.name')
            ->select('batch_students.*')
            ->with('student')
            ->get();

        $session = $attendance->findSession(
            (int) $this->filters['activity_type_id'],
            (int) $this->filters['batch_id'],
            (string) $this->filters['session_date'],
            (string) $this->filters['session_title'],
        );

        $this->sessionId = $session?->id;
        $existing = $session ? $attendance->marksFor($session) : [];

        $this->marks = [];

        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = (bool) ($existing[$row->student_id] ?? true);
        }
    }

    public function markAllPresent(): void
    {
        foreach ($this->roster as $row) {
            $this->marks[$row->student_id] = true;
        }
    }

    public function saveAttendance(ActivityAttendanceService $attendance, array $marks = []): void
    {
        if ($marks !== []) {
            $this->marks = $this->normalizeBooleanMarksFromClient($marks);
        }

        if (! $this->filtersReady() || ! $this->rosterLoaded) {
            Notification::make()
                ->title('Load students first')
                ->warning()
                ->send();

            return;
        }

        try {
            $session = $attendance->findOrCreateSession(
                (int) $this->filters['activity_type_id'],
                (int) $this->filters['batch_id'],
                (string) $this->filters['session_date'],
                (string) $this->filters['session_title'],
                Auth::user(),
            );

            $saved = $attendance->saveMarks($session, $this->marks, Auth::user());

            $this->finishAttendanceSave(
                'Attendance saved',
                "{$saved} student(s) saved for {$session->title} · ".$session->session_date?->format('d M Y'),
            );
        } catch (\Illuminate\Validation\ValidationException $exception) {
            Notification::make()
                ->title('Could not save')
                ->body(collect($exception->errors())->flatten()->first() ?? 'Check the form and try again.')
                ->danger()
                ->send();
        }
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                Select::make('activity_type_id')
                    ->label('Type')
                    ->options(fn (): array => ActivityType::attendanceTypeOptions())
                    ->placeholder('Workshop or Event')
                    ->required()
                    ->native(false)
                    ->searchable()
                    ->helperText('Only types with Marks ✗ (Workshop, Event). Add more under Exam Types.'),
                Select::make('batch_id')
                    ->label('Batch / class')
                    ->options(fn (): array => Batch::query()
                        ->where('status', BatchStatus::Active)
                        ->with('course')
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Batch $batch): array => [
                            $batch->id => "{$batch->name} · {$batch->course?->name}",
                        ])
                        ->all())
                    ->required()
                    ->native(false)
                    ->searchable(),
                DatePicker::make('session_date')
                    ->label('Date')
                    ->required()
                    ->native(false),
                TextInput::make('session_title')
                    ->label('Session name')
                    ->placeholder('e.g. Science fair orientation, Guest lecture')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull()
                    ->helperText('Same name + date + batch reopens saved attendance.'),
            ])
            ->columns(2);
    }

    public function getFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('filtersForm')])
            ->id('sessionAttendanceFilters')
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
        $hasAttendanceTypes = ActivityType::attendanceTypeOptions() !== [];

        return $schema->components([
            Section::make('Select session')
                ->description('Pick workshop or event type, batch, date, and session name — then mark who attended.')
                ->icon(Heroicon::OutlinedUserGroup)
                ->schema([
                    $hasAttendanceTypes
                        ? $this->getFiltersFormComponent()
                        : View::make('filament.pages.partials.session-attendance-no-types'),
                ])
                ->compact(),
            View::make('filament.pages.partials.session-attendance-roster')
                ->viewData(fn (): array => [
                    'rosterLoaded' => $this->rosterLoaded,
                    'roster' => $this->roster,
                    'marks' => $this->marks,
                    'sessionId' => $this->sessionId,
                    'sessionTitle' => $this->filters['session_title'] ?? null,
                ])
                ->visible($hasAttendanceTypes),
        ]);
    }

    protected function filtersReady(): bool
    {
        return filled($this->filters['activity_type_id'] ?? null)
            && filled($this->filters['batch_id'] ?? null)
            && filled($this->filters['session_date'] ?? null)
            && filled(trim((string) ($this->filters['session_title'] ?? '')));
    }
}

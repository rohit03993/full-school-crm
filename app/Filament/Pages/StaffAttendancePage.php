<?php

namespace App\Filament\Pages;

use App\Enums\AttendanceStatus;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Resources\Staff\StaffResource;
use App\Models\StaffAttendance;
use App\Models\User;
use App\Services\Punch\ManualStaffAttendanceService;
use App\Support\AttendanceSourceLabel;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class StaffAttendancePage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Staff attendance';

    protected static ?string $title = 'Staff Attendance';

    protected static ?int $navigationSort = 40;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::AttendanceMark)
            || CrmAccess::can(Auth::user(), CrmPermission::StaffManage);
    }

    public function getSubheading(): ?string
    {
        return 'Manual IN/OUT here, or Face/RFID (Staff ID = device PIN). Open IN auto-closes at the same cutoff as students (default 20:00). Missing Staff ID? Admin → Staff → Assign missing Staff IDs, then Sync to Face API.';
    }

    public function mount(): void
    {
        $this->form->fill([
            'date' => now()->toDateString(),
            'user_id' => null,
        ]);
    }

    public function defaultForm(Schema $schema): Schema
    {
        return $schema->statePath('data');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Filters')
                ->schema([
                    DatePicker::make('date')
                        ->label('Date')
                        ->native(false)
                        ->required()
                        ->live(),
                    Select::make('user_id')
                        ->label('Staff')
                        ->options(fn (): array => User::query()
                            ->where('is_active', true)
                            ->whereHas('staffProfile', fn ($q) => $q
                                ->whereNotNull('employee_code')
                                ->where('employee_code', '!=', ''))
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->nullable()
                        ->native(false)
                        ->live(),
                ])
                ->columns(2),
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('staffAttendanceFilters')
                ->footer([
                    Actions::make([
                        Action::make('openStaffList')
                            ->label('Staff list / Face sync')
                            ->icon(Heroicon::OutlinedUsers)
                            ->color('gray')
                            ->outlined()
                            ->url(StaffResource::getUrl('index')),
                    ])->alignment(Alignment::Start),
                ]),
            View::make('filament.pages.partials.staff-attendance-table')
                ->viewData(function (): array {
                    $rows = $this->rows();

                    return [
                        'rows' => $rows,
                        'summary' => $this->summarizeRows($rows),
                        'missingStaffIdCount' => $this->missingStaffIdCount(),
                        'staffListUrl' => StaffResource::getUrl('index'),
                        'date' => $this->selectedDate(),
                        'canMarkToday' => $this->selectedDate() === now()->toDateString(),
                    ];
                }),
        ]);
    }

    public function markManualIn(int $userId, ManualStaffAttendanceService $manual): void
    {
        $this->markManual($userId, 'IN', $manual);
    }

    public function markManualOut(int $userId, ManualStaffAttendanceService $manual): void
    {
        $this->markManual($userId, 'OUT', $manual);
    }

    protected function markManual(int $userId, string $state, ManualStaffAttendanceService $manual): void
    {
        $date = $this->selectedDate();
        $staffMember = User::query()->with('staffProfile')->find($userId);

        if (! $staffMember) {
            Notification::make()->title('Staff not found')->danger()->send();

            return;
        }

        $result = $state === 'OUT'
            ? $manual->manualOut($staffMember, $date, Auth::user())
            : $manual->manualIn($staffMember, $date, Auth::user());

        if (! $result['ok']) {
            Notification::make()->title($result['message'])->warning()->send();

            return;
        }

        $body = $result['message'];
        if (($result['whatsapp']['queued'] ?? false) === true) {
            $body .= ' WhatsApp queued.';
        }

        Notification::make()
            ->title($staffMember->name)
            ->body($body)
            ->success()
            ->send();
    }

    protected function selectedDate(): string
    {
        $raw = $this->data['date'] ?? now()->toDateString();

        return \Illuminate\Support\Carbon::parse($raw)->toDateString();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    protected function rows(): Collection
    {
        $date = $this->selectedDate();
        $manual = app(ManualStaffAttendanceService::class);

        $query = User::query()
            ->where('is_active', true)
            ->whereHas('staffProfile', fn ($q) => $q->whereNotNull('employee_code')->where('employee_code', '!=', ''))
            ->with([
                'staffProfile',
                'staffAttendances' => fn ($q) => $q->whereDate('attendance_date', $date)->with('markedBy'),
            ])
            ->orderBy('name');

        if (filled($this->data['user_id'] ?? null)) {
            $query->whereKey((int) $this->data['user_id']);
        }

        return $query->get()->map(function (User $user) use ($date, $manual): array {
            /** @var StaffAttendance|null $attendance */
            $attendance = $user->staffAttendances->first();
            $inside = $manual->isInside($user, $date);
            $status = $attendance?->status;
            $statusKey = $this->dayStatusKey($status, $inside);

            return [
                'id' => $user->id,
                'name' => $user->name,
                'employee_code' => $user->staffProfile?->employee_code,
                'designation' => $user->staffProfile?->designation,
                'mobile' => $user->staffProfile?->mobile ?: $user->mobile,
                'status' => $status?->value,
                'status_key' => $statusKey,
                'status_label' => $this->dayStatusLabel($statusKey),
                'checked_in_at' => $attendance?->checked_in_at?->format('H:i:s'),
                'checked_out_at' => $attendance?->checked_out_at?->format('H:i:s'),
                'punch_source' => $attendance?->punch_source,
                'source_label' => AttendanceSourceLabel::for(
                    $attendance?->punch_source,
                    $attendance?->markedBy?->name,
                ),
                'can_in' => ! $inside,
                'can_out' => $inside,
                'date' => $date,
            ];
        });
    }

    /**
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array{total: int, present: int, inside: int, left: int, not_punched: int, leave: int}
     */
    protected function summarizeRows(Collection $rows): array
    {
        return [
            'total' => $rows->count(),
            'present' => $rows->whereIn('status_key', ['inside', 'left'])->count(),
            'inside' => $rows->where('status_key', 'inside')->count(),
            'left' => $rows->where('status_key', 'left')->count(),
            'not_punched' => $rows->where('status_key', 'not_punched')->count(),
            'leave' => $rows->where('status_key', 'leave')->count(),
        ];
    }

    protected function missingStaffIdCount(): int
    {
        return (int) User::query()
            ->where('is_active', true)
            ->where('is_platform_operator', false)
            ->where(function ($query): void {
                $query
                    ->whereDoesntHave('staffProfile')
                    ->orWhereHas('staffProfile', fn ($profile) => $profile
                        ->whereNull('employee_code')
                        ->orWhere('employee_code', ''));
            })
            ->whereHas('roles')
            ->count();
    }

    protected function dayStatusKey(?AttendanceStatus $status, bool $inside): string
    {
        if ($status === AttendanceStatus::Leave) {
            return 'leave';
        }

        if ($inside) {
            return 'inside';
        }

        if ($status === AttendanceStatus::Present) {
            return 'left';
        }

        return 'not_punched';
    }

    protected function dayStatusLabel(string $key): string
    {
        return match ($key) {
            'inside' => 'Present · inside',
            'left' => 'Present · left',
            'leave' => 'Leave',
            default => 'Not punched',
        };
    }
}

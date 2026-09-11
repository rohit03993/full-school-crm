<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Models\Batch;
use App\Models\Student;
use App\Services\AttendanceHubOverviewService;
use App\Services\Punch\ManualBatchAttendanceService;
use App\Support\AttendanceLeaveReasons;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class AttendanceHubPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $title = 'Attendance';

    protected static ?string $slug = 'attendance-hub';

    protected static ?int $navigationSort = 39;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_ACADEMICS;

    public string $overviewDate = '';

    /** @var 'all'|'student'|'staff' */
    public string $feedType = 'all';

    public ?int $classDrillBatchId = null;

    /** @var 'present'|'absent'|null */
    public ?string $classDrillBucket = null;

    public ?int $leaveStudentId = null;

    public string $leaveReasonTag = '';

    public string $leaveReasonCustom = '';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::attendance();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return false;
        }

        return AttendancePage::canAccess() || StaffAttendancePage::canAccess();
    }

    public function mount(): void
    {
        $this->overviewDate = now()->toDateString();
    }

    public function updatedOverviewDate(): void
    {
        $this->closeClassDrill();
        $this->resetPage();
    }

    public function updatedFeedType(): void
    {
        $this->resetPage();
    }

    public function getSubheading(): ?string
    {
        return 'Today’s overview for students and staff, then mark from live punches, manual batch, or staff desk.';
    }

    public function openClassDrill(int $batchId, string $bucket): void
    {
        if (! in_array($bucket, ['present', 'absent'], true)) {
            return;
        }

        $this->classDrillBatchId = $batchId;
        $this->classDrillBucket = $bucket;
        $this->leaveStudentId = null;
        $this->leaveReasonTag = '';
        $this->leaveReasonCustom = '';
    }

    public function closeClassDrill(): void
    {
        $this->classDrillBatchId = null;
        $this->classDrillBucket = null;
        $this->leaveStudentId = null;
        $this->leaveReasonTag = '';
        $this->leaveReasonCustom = '';
    }

    public function startLeaveMark(int $studentId): void
    {
        $this->leaveStudentId = $studentId;
        $this->leaveReasonTag = AttendanceLeaveReasons::tags()[0] ?? 'Personal work';
        $this->leaveReasonCustom = '';
    }

    public function cancelLeaveMark(): void
    {
        $this->leaveStudentId = null;
        $this->leaveReasonTag = '';
        $this->leaveReasonCustom = '';
    }

    public function markHubAbsent(int $studentId): void
    {
        $user = Auth::user();
        if (! $user || $this->classDrillBatchId === null) {
            return;
        }

        $student = Student::query()->find($studentId);
        $batch = Batch::query()->find($this->classDrillBatchId);
        if (! $student || ! $batch) {
            return;
        }

        $result = app(ManualBatchAttendanceService::class)->markAbsent(
            $student,
            $this->resolvedDate(),
            $user,
            $batch,
        );

        $notification = Notification::make()
            ->title($result['ok'] ? 'Marked absent' : 'Could not mark')
            ->body($result['message']);

        if ($result['ok']) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();
    }

    public function confirmHubLeave(): void
    {
        $user = Auth::user();
        if (! $user || $this->classDrillBatchId === null || $this->leaveStudentId === null) {
            return;
        }

        $student = Student::query()->find($this->leaveStudentId);
        $batch = Batch::query()->find($this->classDrillBatchId);
        if (! $student || ! $batch) {
            return;
        }

        $reason = AttendanceLeaveReasons::compose($this->leaveReasonTag, $this->leaveReasonCustom);
        $result = app(ManualBatchAttendanceService::class)->markLeave(
            $student,
            $this->resolvedDate(),
            $user,
            $reason,
            $batch,
        );

        $notification = Notification::make()
            ->title($result['ok'] ? 'Marked on leave' : 'Could not mark leave')
            ->body($result['message']);

        if ($result['ok']) {
            $notification->success();
        } else {
            $notification->danger();
        }

        $notification->send();

        if ($result['ok']) {
            $this->cancelLeaveMark();
        }
    }

    protected function resolvedDate(): string
    {
        return filled($this->overviewDate)
            ? Carbon::parse($this->overviewDate)->toDateString()
            : now()->toDateString();
    }

    public function content(Schema $schema): Schema
    {
        $date = $this->resolvedDate();
        $overviewService = app(AttendanceHubOverviewService::class);

        $overview = $overviewService->overview($date);
        $feed = $overviewService->feed(
            $date,
            $this->feedType,
            $this->getPage(),
            AttendanceHubOverviewService::FEED_PER_PAGE,
        );

        $classDrill = null;
        if ($this->classDrillBatchId && $this->classDrillBucket) {
            $classDrill = $overviewService->classBucketRoster(
                $this->classDrillBatchId,
                $date,
                $this->classDrillBucket,
            );
        }

        $cards = [];

        if (AttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Students — live punches',
                'description' => 'Biometric / Face IN–OUT for today\'s classes. Student Roll No. = device PIN.',
                'url' => AttendancePage::getUrl(['mode' => 'live']),
                'badge' => 'Students',
                'tone' => 'primary',
            ];
            $cards[] = [
                'title' => 'Students — manual batch',
                'description' => 'Mark a class section by hand when the machine is not used.',
                'url' => AttendancePage::getUrl(['mode' => 'manual']),
                'badge' => 'Students',
            ];
        }

        if (StaffAttendancePage::canAccess()) {
            $cards[] = [
                'title' => 'Staff attendance',
                'description' => 'IN/OUT for teachers and office staff. Staff ID = device PIN = Face ID.',
                'url' => StaffAttendancePage::getUrl(),
                'badge' => 'Staff',
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.attendance-hub-overview')
                ->viewData([
                    'overview' => $overview,
                    'feed' => $feed,
                    'feedType' => $this->feedType,
                    'cards' => $cards,
                    'classDrill' => $classDrill,
                    'leaveStudentId' => $this->leaveStudentId,
                    'leaveReasonTag' => $this->leaveReasonTag,
                    'leaveReasonCustom' => $this->leaveReasonCustom,
                    'leaveTags' => AttendanceLeaveReasons::tags(),
                ]),
        ]);
    }
}

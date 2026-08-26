<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Services\AttendanceHubOverviewService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
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

    public function content(Schema $schema): Schema
    {
        $date = filled($this->overviewDate)
            ? Carbon::parse($this->overviewDate)->toDateString()
            : now()->toDateString();

        $overview = app(AttendanceHubOverviewService::class)->overview($date);
        $feed = app(AttendanceHubOverviewService::class)->feed(
            $date,
            $this->feedType,
            $this->getPage(),
            AttendanceHubOverviewService::FEED_PER_PAGE,
        );

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
                ]),
        ]);
    }
}

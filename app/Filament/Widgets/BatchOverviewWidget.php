<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendanceHubPage;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\InstituteTerminology;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class BatchOverviewWidget extends Widget
{
    use UsesDashboardFilters;

    /**
     * Academic staff run attendance day to day, so they get this board too —
     * money columns stay behind the fee permission.
     */
    public static function canView(): bool
    {
        return FeatureGate::anyEnabled(LicenseFeature::Attendance, LicenseFeature::Fees)
            && CrmAccess::canAny(
                Auth::user(),
                CrmPermission::DashboardOwnerStats,
                CrmPermission::AttendanceMark,
            );
    }

    protected static ?int $sort = -8;

    protected string $view = 'filament.widgets.batch-overview';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filters = $this->dashboardFilters();
        $user = Auth::user();

        return [
            'overview' => app(CrmDashboardService::class)->batchOverview($filters),
            'attendanceUrl' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
            'showAttendance' => FeatureGate::enabled(LicenseFeature::Attendance),
            'showFees' => FeatureGate::enabled(LicenseFeature::Fees) && CrmAccess::canViewFees($user),
            'batchLabel' => InstituteTerminology::label('batch'),
            'isToday' => $filters->isToday(),
        ];
    }
}

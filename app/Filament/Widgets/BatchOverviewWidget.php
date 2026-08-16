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

    public static function canView(): bool
    {
        return FeatureGate::enabled(LicenseFeature::Attendance)
            && CrmAccess::canAny(
                Auth::user(),
                CrmPermission::DashboardOwnerStats,
                CrmPermission::AttendanceMark,
            );
    }

    protected static ?int $sort = -7;

    protected string $view = 'filament.widgets.batch-overview';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $filters = $this->dashboardFilters();

        return [
            'overview' => app(CrmDashboardService::class)->batchOverview($filters),
            'attendanceUrl' => AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null,
            'batchLabel' => InstituteTerminology::label('batch'),
            'isToday' => $filters->isToday(),
        ];
    }
}

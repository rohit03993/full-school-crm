<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendanceHubPage;
use App\Filament\Pages\FeesDashboardPage;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Filament\Widgets\Concerns\VisibleWithCrmPermission;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CrmFinanceStatsWidget extends StatsOverviewWidget
{
    use UsesDashboardFilters;
    use VisibleWithCrmPermission;

    public static function canView(): bool
    {
        return FeatureGate::enabled(LicenseFeature::Fees)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardFinanceStats);
    }

    protected static function crmPermissionForWidget(): CrmPermission
    {
        return CrmPermission::DashboardFinanceStats;
    }

    protected static ?int $sort = 2;

    protected ?string $heading = 'Students & money';

    protected int | string | array $columnSpan = 'full';

    public function getDescription(): ?string
    {
        return 'Following the filters above · '.$this->dashboardFilters()->rangeLabel();
    }

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $filters = $this->dashboardFilters();
        $service = app(CrmDashboardService::class);
        $stats = $service->stats($filters);
        $fees = $service->feeSummary($filters);
        $asOf = $stats['as_of_label'];

        $attendanceRate = $stats['attendance_students_in_batches'] > 0
            ? round(($stats['attendance_present_today'] / $stats['attendance_students_in_batches']) * 100)
            : 0;

        return [
            Stat::make('Enrolled students', (string) $stats['active_students'])
                ->description($stats['active_batches'].' active batches in scope')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make('Present '.$asOf, (string) $stats['attendance_present_today'])
                ->description($attendanceRate.'% of '.$stats['attendance_students_in_batches'].' · '.$stats['attendance_marked_today'].' marked')
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color($attendanceRate >= 75 ? 'success' : ($attendanceRate > 0 ? 'warning' : 'gray'))
                ->url(AttendanceHubPage::canAccess() ? AttendanceHubPage::getUrl() : null),
            Stat::make('Collected', '₹'.number_format($stats['range_fee_collection'], 0))
                ->description('₹'.number_format($stats['fee_collection_today'], 0).' of it today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success')
                ->url(FeesDashboardPage::canAccess() ? FeesDashboardPage::getUrl() : null),
            Stat::make('Pending fees', '₹'.number_format($stats['pending_fees_total'], 0))
                ->description($fees['overdue_students_count'].' students overdue · ₹'.number_format($fees['overdue_amount'], 0))
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color($fees['overdue_amount'] > 0 ? 'danger' : 'warning')
                ->url(FeesDashboardPage::canAccess() ? FeesDashboardPage::getUrl() : null),
        ];
    }
}

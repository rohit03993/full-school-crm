<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendancePage;
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

    protected ?string $heading = 'Students & Finance';

    protected ?string $description = 'Enrollments, collections, and attendance today';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $stats = app(CrmDashboardService::class)->stats();

        return [
            Stat::make('Active Students', (string) $stats['active_students'])
                ->description('Currently enrolled')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success'),
            Stat::make('Present Today', (string) $stats['attendance_present_today'])
                ->description("{$stats['attendance_marked_today']} marked of {$stats['attendance_students_in_batches']} in batches")
                ->descriptionIcon(Heroicon::OutlinedCheckCircle)
                ->color('primary')
                ->url(AttendancePage::getUrl()),
            Stat::make('Fee Collection Today', '₹'.number_format($stats['fee_collection_today'], 2))
                ->description('Payments recorded today')
                ->descriptionIcon(Heroicon::OutlinedBanknotes)
                ->color('success'),
            Stat::make('Pending Fees', '₹'.number_format($stats['pending_fees_total'], 2))
                ->description('Outstanding across all students')
                ->descriptionIcon(Heroicon::OutlinedExclamationTriangle)
                ->color('warning'),
        ];
    }
}

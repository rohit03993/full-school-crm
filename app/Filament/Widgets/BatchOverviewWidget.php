<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\AttendancePage;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class BatchOverviewWidget extends Widget
{
    use VisibleToSuperAdminOnly;

    public static function canView(): bool
    {
        return FeatureGate::anyEnabled(LicenseFeature::Attendance, LicenseFeature::Fees)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }

    protected static ?int $sort = -8;

    protected string $view = 'filament.widgets.batch-overview';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $overview = app(CrmDashboardService::class)->batchOverview();

        return [
            'overview' => $overview,
            'attendanceUrl' => AttendancePage::getUrl(),
            'showAttendance' => FeatureGate::enabled(LicenseFeature::Attendance),
            'showFees' => FeatureGate::enabled(LicenseFeature::Fees),
        ];
    }
}

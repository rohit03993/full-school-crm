<?php

namespace App\Filament\Widgets;

use App\Filament\Pages\BatchAttendancePage;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\Widget;

class BatchOverviewWidget extends Widget
{
    use VisibleToSuperAdminOnly;

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
            'attendanceUrl' => BatchAttendancePage::getUrl(),
        ];
    }
}

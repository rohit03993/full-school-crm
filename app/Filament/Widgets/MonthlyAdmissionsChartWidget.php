<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardCharts;
use App\Filament\Widgets\Concerns\UsesDashboardFilters;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\CrmDashboardService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class MonthlyAdmissionsChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use UsesDashboardFilters;
    use VisibleToSuperAdminOnly;

    public static function canView(): bool
    {
        return FeatureGate::enabled(LicenseFeature::Admissions)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 5;

    protected ?string $heading = 'Admissions trend';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public function getDescription(): ?string
    {
        return 'Month by month, ending '.$this->dashboardFilters()->to->format('M Y');
    }

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->monthlyAdmissions($this->dashboardFilters());

        return [
            'datasets' => [
                [
                    'label' => 'Approvals',
                    'data' => $chart['data'],
                    'backgroundColor' => '#f59e0b',
                    'borderRadius' => 6,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return $this->dashboardChartOptions(showLegend: false);
    }
}

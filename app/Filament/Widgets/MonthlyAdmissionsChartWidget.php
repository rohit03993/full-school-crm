<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardCharts;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\ChartWidget;

class MonthlyAdmissionsChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use VisibleToSuperAdminOnly;

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 5;

    protected ?string $heading = 'Monthly Admissions';

    protected ?string $description = 'Last 6 months';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->monthlyAdmissions();

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

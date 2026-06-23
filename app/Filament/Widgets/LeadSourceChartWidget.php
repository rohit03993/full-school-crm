<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardCharts;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\ChartWidget;

class LeadSourceChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use VisibleToSuperAdminOnly;

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 7;

    protected ?string $heading = 'Lead Sources';

    protected ?string $description = 'All-time breakdown';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->leadSourceBreakdown();

        return [
            'datasets' => [
                [
                    'data' => $chart['data'],
                    'backgroundColor' => ['#f59e0b', '#3b82f6', '#10b981', '#8b5cf6', '#ef4444', '#6b7280'],
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return $this->dashboardChartOptions(showScales: false);
    }
}

<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardCharts;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\ChartWidget;

class CourseAdmissionsChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use VisibleToSuperAdminOnly;

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 8;

    protected ?string $heading = 'Course-wise Admissions';

    protected ?string $description = 'Top courses';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->courseWiseAdmissions();

        return [
            'datasets' => [
                [
                    'label' => 'Approvals',
                    'data' => $chart['data'],
                    'backgroundColor' => '#d97706',
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

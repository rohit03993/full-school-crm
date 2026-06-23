<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardCharts;
use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\CrmDashboardService;
use Filament\Widgets\ChartWidget;

class MonthlyFeeCollectionChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use VisibleToSuperAdminOnly;

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 6;

    protected ?string $heading = 'Monthly Fee Collection';

    protected ?string $description = 'Last 6 months';

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->monthlyFeeCollection();

        return [
            'datasets' => [
                [
                    'label' => 'Collected (₹)',
                    'data' => $chart['data'],
                    'borderColor' => '#059669',
                    'backgroundColor' => 'rgba(5, 150, 105, 0.12)',
                    'fill' => true,
                    'tension' => 0.35,
                    'pointRadius' => 3,
                    'pointHoverRadius' => 5,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return $this->dashboardChartOptions(showLegend: false);
    }
}

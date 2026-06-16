<?php

namespace App\Filament\Widgets\Concerns;

trait InteractsWithDashboardCharts
{
    /**
     * @return array<string, mixed>
     */
    protected function dashboardChartOptions(bool $showLegend = true, bool $showScales = true): array
    {
        $options = [
            'maintainAspectRatio' => false,
            'plugins' => [
                'legend' => [
                    'display' => $showLegend,
                    'position' => 'bottom',
                    'labels' => [
                        'usePointStyle' => true,
                        'boxWidth' => 8,
                        'padding' => 16,
                    ],
                ],
            ],
        ];

        if ($showScales) {
            $options['scales'] = [
                'y' => [
                    'beginAtZero' => true,
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                ],
                'x' => [
                    'grid' => ['display' => false],
                    'border' => ['display' => false],
                ],
            ];
        }

        return $options;
    }
}

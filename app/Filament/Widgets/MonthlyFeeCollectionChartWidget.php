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

class MonthlyFeeCollectionChartWidget extends ChartWidget
{
    use InteractsWithDashboardCharts;
    use UsesDashboardFilters;
    use VisibleToSuperAdminOnly;

    public static function canView(): bool
    {
        return FeatureGate::enabled(LicenseFeature::Fees)
            && CrmAccess::can(Auth::user(), CrmPermission::DashboardOwnerStats);
    }

    protected ?string $maxHeight = '280px';

    protected static ?int $sort = 6;

    protected ?string $heading = 'Fee collection trend';

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
        $chart = app(CrmDashboardService::class)->monthlyFeeCollection($this->dashboardFilters());

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

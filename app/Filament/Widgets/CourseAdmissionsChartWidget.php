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
use App\Support\InstituteTerminology;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\Auth;

class CourseAdmissionsChartWidget extends ChartWidget
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

    protected static ?int $sort = 8;

    protected int | string | array $columnSpan = [
        'default' => 'full',
        'lg' => 1,
    ];

    public function getHeading(): ?string
    {
        return 'Admissions by '.strtolower(InstituteTerminology::label('course'));
    }

    public function getDescription(): ?string
    {
        return 'Top 8 · '.$this->dashboardFilters()->rangeLabel();
    }

    protected function getData(): array
    {
        $chart = app(CrmDashboardService::class)->courseWiseAdmissions($this->dashboardFilters());

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

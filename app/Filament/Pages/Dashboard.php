<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CrmFinanceStatsWidget;
use App\Filament\Widgets\CrmLeadStatsWidget;
use App\Filament\Widgets\DashboardAttentionWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\DashboardTodayPulseWidget;
use App\Filament\Widgets\LicenseStatusWidget;
use App\Support\CrmMenuLabels;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = -200;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::dashboard();
    }

    public function getTitle(): string | Htmlable
    {
        return '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            LicenseStatusWidget::class,
            DashboardHeroWidget::class,
            DashboardAttentionWidget::class,
            DashboardTodayPulseWidget::class,
            CrmFinanceStatsWidget::class,
            CrmLeadStatsWidget::class,
        ];
    }
}

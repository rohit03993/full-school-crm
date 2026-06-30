<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\VisibleToSuperAdminOnly;
use App\Services\LicenseService;
use Filament\Widgets\Widget;

class LicenseStatusWidget extends Widget
{
    use VisibleToSuperAdminOnly;

    protected static bool $isLazy = false;

    protected static ?int $sort = -15;

    protected string $view = 'filament.widgets.license-status';

    protected int | string | array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return app(LicenseService::class)->dashboardSummary();
    }
}

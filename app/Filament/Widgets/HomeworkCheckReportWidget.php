<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\HomeworkCheckReportService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class HomeworkCheckReportWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = -7;

    protected string $view = 'filament.widgets.homework-check-report';

    protected int | string | array $columnSpan = 'full';

    public string $reportDate = '';

    public static function canView(): bool
    {
        $user = Auth::user();

        return $user !== null
            && FeatureGate::enabled(LicenseFeature::Homework)
            && CrmAccess::can($user, CrmPermission::HomeworkManage);
    }

    public function mount(): void
    {
        $this->reportDate = now()->toDateString();
    }

    public function updatedReportDate(): void
    {
        $this->reportDate = app(HomeworkCheckReportService::class)->normalizeDate($this->reportDate);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return app(HomeworkCheckReportService::class)->presentation($this->reportDate);
    }
}

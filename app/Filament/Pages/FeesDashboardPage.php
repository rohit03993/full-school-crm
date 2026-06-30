<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Services\FeesDashboardService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FeesDashboardPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Fees';

    protected static ?string $title = 'Fees dashboard';

    protected static ?int $navigationSort = 25;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    protected string $view = 'filament.pages.fees-dashboard';

    /**
     * @var array<string, float|int>
     */
    public array $summary = [];

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $defaulters;

    public function boot(): void
    {
        $this->defaulters = collect();
    }

    public static function canAccess(): bool
    {
        return CrmAccess::canAny(Auth::user(), CrmPermission::FeesCollect, CrmPermission::FeesAdjustStructure);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('fees.dashboard');
    }

    public function mount(FeesDashboardService $fees): void
    {
        $this->refreshData($fees);
    }

    public function refreshDashboard(FeesDashboardService $fees): void
    {
        $this->refreshData($fees);
    }

    protected function refreshData(FeesDashboardService $fees): void
    {
        $this->summary = $fees->summary();
        $this->defaulters = $fees->defaulters();
    }
}

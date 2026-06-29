<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Services\InstituteVisitsService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class CampusVisitsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Visit Log';

    protected static ?string $title = 'Visit Log';

    protected static ?int $navigationSort = 22;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_LEADS;

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $enrollmentFilter = 'all';

    public string $search = '';

    public int $perPage = CrmPagination::PER_PAGE;

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('campus.visits');
    }

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::VisitsViewAll);
    }

    public function setPeriodToday(): void
    {
        $this->dateFrom = now()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function setPeriodThisMonth(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->resetPage();
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    public function updatedEnrollmentFilter(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function content(Schema $schema): Schema
    {
        $service = app(InstituteVisitsService::class);
        [$from, $to] = $service->resolveDateRange($this->dateFrom, $this->dateTo);

        return $schema->components([
            View::make('filament.pages.partials.campus-visits')
                ->viewData(fn (): array => [
                    'visits' => $service->paginate(
                        $from,
                        $to,
                        $this->enrollmentFilter,
                        $this->search,
                        page: $this->getPage(),
                    ),
                    'stats' => $service->stats($from, $to, $this->enrollmentFilter),
                    'dateFrom' => $from->toDateString(),
                    'dateTo' => $to->toDateString(),
                    'enrollmentFilter' => $this->enrollmentFilter,
                    'search' => $this->search,
                    'periodLabel' => $this->periodLabel($from, $to),
                ]),
        ]);
    }

    protected function periodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return $from->format('d M Y');
        }

        return $from->format('d M Y').' – '.$to->format('d M Y');
    }
}

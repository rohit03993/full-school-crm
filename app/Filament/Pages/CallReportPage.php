<?php

namespace App\Filament\Pages;

use App\Enums\CallStatus;
use App\Enums\CrmPermission;
use App\Enums\VisitStatus;
use App\Support\CrmAccess;
use App\Services\CallReportService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class CallReportPage extends Page
{
    use WithPagination;
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Call Report';

    protected static ?string $title = 'Call Report';

    protected static ?int $navigationSort = 20;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_CALLS;

    public static function canAccess(): bool
    {
        return CrmAccess::canAny(Auth::user(), CrmPermission::LeadsCall, CrmPermission::ReportsView);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('call.report');
    }

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $connectionFilter = 'all';

    public ?string $callStatusFilter = null;

    public ?string $visitStatusFilter = null;

    public string $callTypeFilter = 'all';

    public string $search = '';

    public ?int $staffUserId = null;

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{total: int, connected: int, not_connected: int, new_calls: int, followup_calls: int}
     */
    public array $summary = [
        'total' => 0,
        'connected' => 0,
        'not_connected' => 0,
        'new_calls' => 0,
        'followup_calls' => 0,
    ];

    public bool $canViewAllStaff = false;

    public function mount(CallReportService $report): void
    {
        $this->canViewAllStaff = $report->canViewAllStaff(Auth::user());
        $this->applyDefaultDates();
        $this->loadReport($report);
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedConnectionFilter(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedCallStatusFilter(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedVisitStatusFilter(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedCallTypeFilter(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function updatedStaffUserId(): void
    {
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    public function resetFilters(): void
    {
        $this->connectionFilter = 'all';
        $this->callStatusFilter = null;
        $this->visitStatusFilter = null;
        $this->callTypeFilter = 'all';
        $this->search = '';
        $this->staffUserId = null;
        $this->applyDefaultDates();
        $this->resetPage();
        $this->loadReport(app(CallReportService::class));
    }

    protected function applyDefaultDates(): void
    {
        $this->dateFrom = today()->subDays(6)->toDateString();
        $this->dateTo = today()->toDateString();
    }

    protected function loadReport(CallReportService $report): void
    {
        $viewer = Auth::user();
        $filters = $report->normalizeFilters([
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'connection' => $this->connectionFilter,
            'call_status' => $this->callStatusFilter,
            'visit_status' => $this->visitStatusFilter,
            'call_type' => $this->callTypeFilter,
            'search' => $this->search,
            'staff_user_id' => $this->staffUserId,
        ], $viewer);

        $this->dateFrom = $filters['from'];
        $this->dateTo = $filters['to'];
        $this->summary = $report->summary($filters, $viewer);
    }

    /**
     * @return array<string, mixed>
     */
    protected function reportFilters(CallReportService $report): array
    {
        return $report->normalizeFilters([
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'connection' => $this->connectionFilter,
            'call_status' => $this->callStatusFilter,
            'visit_status' => $this->visitStatusFilter,
            'call_type' => $this->callTypeFilter,
            'search' => $this->search,
            'staff_user_id' => $this->staffUserId,
        ], Auth::user());
    }

    protected function callsPaginator(CallReportService $report): LengthAwarePaginator
    {
        return $report->calls($this->reportFilters($report), Auth::user(), page: $this->getPage());
    }

    public function content(Schema $schema): Schema
    {
        $report = app(CallReportService::class);

        return $schema->components([
            View::make('filament.pages.partials.call-report')
                ->viewData(function () use ($report): array {
                    $calls = $this->callsPaginator($report);

                    return [
                        'summary' => $this->summary,
                        'calls' => $calls,
                        'canViewAllStaff' => $this->canViewAllStaff,
                        'staffOptions' => $report->staffOptions(),
                        'notConnectedOptions' => CallStatus::notConnectedOptions(),
                        'visitStatusOptions' => collect(VisitStatus::cases())
                            ->mapWithKeys(fn (VisitStatus $status): array => [$status->value => $status->label()])
                            ->all(),
                        'reportService' => $report,
                        'firstCallIds' => $report->firstCallIdsFor($calls),
                    ];
                }),
        ]);
    }
}

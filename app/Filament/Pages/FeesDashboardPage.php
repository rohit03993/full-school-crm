<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Services\AccountingLedgerService;
use App\Services\FeesDashboardService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Support\DashboardFilters;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FeesDashboardPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::fees();
    }

    public function getTitle(): string
    {
        return 'Fees dashboard';
    }

    protected static ?int $navigationSort = 25;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    protected string $view = 'filament.pages.fees-dashboard';

    public string $activeTab = 'overview';

    public string $rangePreset = DashboardFilters::RANGE_MONTH;

    /**
     * @var array<string, mixed>
     */
    public array $summary = [];

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $defaulters;

    public int $defaultersPage = 1;

    public int $defaultersTotal = 0;

    public int $defaultersLastPage = 1;

    public int $ledgerEntriesPage = 1;

    public int $ledgerEntriesTotal = 0;

    public int $ledgerEntriesLastPage = 1;

    /** @var 'all'|'collections'|'late_fees'|'cancels' */
    public string $ledgerEntryFilter = 'collections';

    public ?string $fromDate = null;

    public ?string $toDate = null;

    /**
     * @var array<string, mixed>
     */
    public array $ledgerSummary = [];

    public function boot(): void
    {
        $this->defaulters = collect();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return CrmAccess::canViewFees(Auth::user());
    }

    public function getSubheading(): ?string
    {
        return match ($this->activeTab) {
            'ledger' => 'Money received for '.$this->periodLabel().'. Late fees charged are hidden by default — use the filter below to show them.',
            default => 'Track collections, discounts, and overdue balances for '.$this->periodLabel().'.',
        };
    }

    public function mount(FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        if (request()->query('tab') === 'ledger') {
            $this->activeTab = 'ledger';
        }

        $this->applyPresetDates($this->rangePreset);
        $this->refreshData($fees);
        $this->refreshLedger($ledger);
    }

    public function setActiveTab(string $tab): void
    {
        if (! in_array($tab, ['overview', 'ledger'], true)) {
            return;
        }

        $this->activeTab = $tab;
    }

    public function setRangePreset(string $preset, FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        if (! in_array($preset, [
            DashboardFilters::RANGE_TODAY,
            DashboardFilters::RANGE_WEEK,
            DashboardFilters::RANGE_MONTH,
            DashboardFilters::RANGE_CUSTOM,
        ], true)) {
            return;
        }

        $this->rangePreset = $preset;

        if ($preset !== DashboardFilters::RANGE_CUSTOM) {
            $this->applyPresetDates($preset);
        }

        $this->defaultersPage = 1;
        $this->ledgerEntriesPage = 1;
        $this->refreshData($fees);
        $this->refreshLedger($ledger);
    }

    public function applyPeriodFilters(FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        $this->rangePreset = DashboardFilters::RANGE_CUSTOM;
        $this->normalizeDates();
        $this->defaultersPage = 1;
        $this->ledgerEntriesPage = 1;
        $this->refreshData($fees);
        $this->refreshLedger($ledger);
    }

    public function refreshDashboard(FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        $this->refreshData($fees);
        $this->refreshLedger($ledger);
    }

    public function previousDefaultersPage(FeesDashboardService $fees): void
    {
        $this->defaultersPage = max(1, $this->defaultersPage - 1);
        $this->refreshDefaulters($fees);
    }

    public function nextDefaultersPage(FeesDashboardService $fees): void
    {
        $this->defaultersPage = min($this->defaultersLastPage, $this->defaultersPage + 1);
        $this->refreshDefaulters($fees);
    }

    public function previousLedgerEntriesPage(): void
    {
        $this->ledgerEntriesPage = max(1, $this->ledgerEntriesPage - 1);
    }

    public function nextLedgerEntriesPage(): void
    {
        $this->ledgerEntriesPage = min($this->ledgerEntriesLastPage, $this->ledgerEntriesPage + 1);
    }

    public function setLedgerEntryFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'collections', 'late_fees', 'cancels'], true)) {
            return;
        }

        $this->ledgerEntryFilter = $filter;
        $this->ledgerEntriesPage = 1;
        $this->refreshLedger(app(AccountingLedgerService::class));
    }

    protected function refreshData(FeesDashboardService $fees): void
    {
        $this->normalizeDates();
        [$from, $to] = $this->periodBounds();

        $this->summary = $fees->overview($from, $to);
        $this->refreshDefaulters($fees, $to);
    }

    protected function refreshDefaulters(FeesDashboardService $fees, ?Carbon $asOf = null): void
    {
        $asOf ??= $this->periodBounds()[1];
        $page = $fees->paginateDefaulters($asOf, $this->defaultersPage, CrmPagination::PER_PAGE);

        $this->defaulters = $page['rows'];
        $this->defaultersTotal = $page['total'];
        $this->defaultersPage = $page['page'];
        $this->defaultersLastPage = $page['last_page'];
    }

    public function refreshLedger(AccountingLedgerService $ledger): void
    {
        $this->normalizeDates();
        [$from, $to] = $this->periodBounds();

        $summary = $ledger->feeLedgerSummary($from->copy()->startOfDay(), $to->copy()->endOfDay());
        $summary['collection_rows'] = collect($summary['collection_rows'])->values()->all();
        $summary['income_rows'] = collect($summary['income_rows'])->values()->all();

        $this->ledgerSummary = $summary;
        $types = $this->ledgerReferenceTypes($ledger);
        $this->ledgerEntriesTotal = $ledger->countEntries(
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
            $types,
        );
        $this->ledgerEntriesLastPage = max(1, (int) ceil($this->ledgerEntriesTotal / CrmPagination::PER_PAGE));
        $this->ledgerEntriesPage = min(max(1, $this->ledgerEntriesPage), $this->ledgerEntriesLastPage);
    }

    /**
     * @return Collection<int, array{entry: \App\Models\AccountingJournalEntry, lines: Collection<int, \App\Support\FeeLedgerPresentation>, student: array{id: int, name: string}|null}>
     */
    public function getPresentedEntries(): Collection
    {
        $this->normalizeDates();
        [$from, $to] = $this->periodBounds();

        $ledger = app(AccountingLedgerService::class);

        return $ledger->presentEntries($ledger->recentEntries(
            CrmPagination::PER_PAGE,
            $from->copy()->startOfDay(),
            $to->copy()->endOfDay(),
            $this->ledgerEntriesPage,
            $this->ledgerReferenceTypes($ledger),
        ));
    }

    /**
     * @return list<\App\Enums\AccountingReferenceType>|null
     */
    protected function ledgerReferenceTypes(AccountingLedgerService $ledger): ?array
    {
        if ($this->ledgerEntryFilter === 'all') {
            return null;
        }

        return $ledger->referenceTypesForLedgerFilter($this->ledgerEntryFilter);
    }

    /**
     * @return list<array{key: string, label: string, hint: string}>
     */
    public function ledgerEntryFilterOptions(): array
    {
        return [
            ['key' => 'collections', 'label' => 'Collections', 'hint' => 'Receipts & cancels'],
            ['key' => 'late_fees', 'label' => 'Late fees', 'hint' => 'Charges added — no money received'],
            ['key' => 'cancels', 'label' => 'Cancels', 'hint' => 'Cancelled receipts'],
            ['key' => 'all', 'label' => 'All', 'hint' => 'Everything'],
        ];
    }

    public function applyLedgerFilters(FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        $this->applyPeriodFilters($fees, $ledger);
    }

    public function periodLabel(): string
    {
        return match ($this->rangePreset) {
            DashboardFilters::RANGE_TODAY => 'today',
            DashboardFilters::RANGE_WEEK => 'the last 7 days',
            DashboardFilters::RANGE_MONTH => 'this month',
            default => filled($this->fromDate) && filled($this->toDate)
                ? Carbon::parse($this->fromDate)->format('d M').' – '.Carbon::parse($this->toDate)->format('d M Y')
                : 'the selected period',
        };
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function periodBounds(): array
    {
        $from = Carbon::parse((string) $this->fromDate)->startOfDay();
        $to = Carbon::parse((string) $this->toDate)->startOfDay();

        return app(FeesDashboardService::class)->normalizeRange($from, $to);
    }

    protected function applyPresetDates(string $preset): void
    {
        $today = today();

        [$from, $to] = match ($preset) {
            DashboardFilters::RANGE_TODAY => [$today->copy(), $today->copy()],
            DashboardFilters::RANGE_WEEK => [$today->copy()->subDays(6), $today->copy()],
            default => [$today->copy()->startOfMonth(), $today->copy()],
        };

        $this->fromDate = $from->toDateString();
        $this->toDate = $to->toDateString();
    }

    protected function normalizeDates(): void
    {
        if (! filled($this->fromDate)) {
            $this->fromDate = now()->startOfMonth()->toDateString();
        }

        if (! filled($this->toDate)) {
            $this->toDate = now()->toDateString();
        }

        if (Carbon::parse($this->toDate)->lt(Carbon::parse($this->fromDate))) {
            [$this->fromDate, $this->toDate] = [$this->toDate, $this->fromDate];
        }
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function rangePresetOptions(): array
    {
        return [
            ['key' => DashboardFilters::RANGE_TODAY, 'label' => 'Today'],
            ['key' => DashboardFilters::RANGE_WEEK, 'label' => 'Last 7 days'],
            ['key' => DashboardFilters::RANGE_MONTH, 'label' => 'This month'],
            ['key' => DashboardFilters::RANGE_CUSTOM, 'label' => 'Custom'],
        ];
    }
}

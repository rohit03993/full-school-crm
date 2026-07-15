<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Services\AccountingLedgerService;
use App\Services\FeesDashboardService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FeesDashboardPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::fees();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::fees();
    }

    protected static ?int $navigationSort = 25;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    protected string $view = 'filament.pages.fees-dashboard';

    public string $activeTab = 'overview';

    /**
     * @var array<string, float|int>
     */
    public array $summary = [];

    /**
     * @var Collection<int, array<string, mixed>>
     */
    public Collection $defaulters;

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
            'ledger' => 'Collections journal for the selected period. Receipts show as credits (money received).',
            default => CrmHint::text('fees.dashboard'),
        };
    }

    public function mount(FeesDashboardService $fees, AccountingLedgerService $ledger): void
    {
        if (request()->query('tab') === 'ledger') {
            $this->activeTab = 'ledger';
        }

        $this->fromDate ??= now()->startOfMonth()->toDateString();
        $this->toDate ??= now()->toDateString();

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

    public function refreshDashboard(FeesDashboardService $fees): void
    {
        $this->refreshData($fees);
    }

    protected function refreshData(FeesDashboardService $fees): void
    {
        $this->summary = $fees->summary();
        $this->defaulters = $fees->defaulters()->values();
    }

    public function refreshLedger(AccountingLedgerService $ledger): void
    {
        $from = filled($this->fromDate) ? Carbon::parse($this->fromDate)->startOfDay() : null;
        $to = filled($this->toDate) ? Carbon::parse($this->toDate)->endOfDay() : null;

        $summary = $ledger->feeLedgerSummary($from, $to);
        $summary['collection_rows'] = collect($summary['collection_rows'])->values()->all();
        $summary['income_rows'] = collect($summary['income_rows'])->values()->all();

        $this->ledgerSummary = $summary;
    }

    /**
     * @return Collection<int, array{entry: \App\Models\AccountingJournalEntry, lines: Collection<int, \App\Support\FeeLedgerPresentation>}>
     */
    public function getPresentedEntries(): Collection
    {
        $ledger = app(AccountingLedgerService::class);
        $from = filled($this->fromDate) ? Carbon::parse($this->fromDate)->startOfDay() : null;
        $to = filled($this->toDate) ? Carbon::parse($this->toDate)->endOfDay() : null;

        return $ledger->presentEntries($ledger->recentEntries(50, $from, $to));
    }

    public function applyLedgerFilters(AccountingLedgerService $ledger): void
    {
        $this->refreshLedger($ledger);
    }
}

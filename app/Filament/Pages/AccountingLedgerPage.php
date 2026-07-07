<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\AccountingLedgerService;
use App\Support\CrmAccess;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class AccountingLedgerPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static ?string $navigationLabel = 'Accounting ledger';

    protected static ?string $title = 'Accounting ledger';

    protected static ?int $navigationSort = 26;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    protected string $view = 'filament.pages.accounting-ledger';

    public ?string $fromDate = null;

    public ?string $toDate = null;

    /**
     * @var array<string, float|int>
     */
    public array $summary = [];

    /**
     * @var Collection<int, \App\Models\AccountingJournalEntry>
     */
    public Collection $entries;

    public function boot(): void
    {
        $this->entries = collect();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return CrmAccess::canAny(Auth::user(), CrmPermission::DashboardFinanceStats, CrmPermission::FeesCollect);
    }

    public function getSubheading(): ?string
    {
        return 'Double-entry journal from fee receipts and late-fee accruals.';
    }

    public function mount(AccountingLedgerService $ledger): void
    {
        $this->fromDate ??= now()->startOfMonth()->toDateString();
        $this->toDate ??= now()->toDateString();
        $this->refreshLedger($ledger);
    }

    public function refreshLedger(AccountingLedgerService $ledger): void
    {
        $from = filled($this->fromDate) ? Carbon::parse($this->fromDate)->startOfDay() : null;
        $to = filled($this->toDate) ? Carbon::parse($this->toDate)->endOfDay() : null;

        $this->summary = $ledger->summary($from, $to);
        $this->entries = $ledger->recentEntries(50, $from, $to);
    }

    public function applyFilters(AccountingLedgerService $ledger): void
    {
        $this->refreshLedger($ledger);
    }
}

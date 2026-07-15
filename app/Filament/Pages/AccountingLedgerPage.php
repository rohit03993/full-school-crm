<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Auth;

class AccountingLedgerPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.accounting-ledger-redirect';

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return CrmAccess::canViewFees(Auth::user());
    }

    public function mount(): void
    {
        $this->redirect(FeesDashboardPage::getUrl(['tab' => 'ledger']), navigate: true);
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class FeesHubPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $title = 'Fees';

    protected static ?string $slug = 'fees-hub';

    protected static ?int $navigationSort = 25;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::fees();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return FeesDashboardPage::canAccess()
            || BulkMiscChargePage::canAccess()
            || MiscChargeAdjustmentRequestsPage::canAccess()
            || PaymentCancellationRequestsPage::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Dashboard, bulk charges, adjustments, and payment cancellations.';
    }

    public function content(Schema $schema): Schema
    {
        $cards = [];

        if (FeesDashboardPage::canAccess()) {
            $cards[] = [
                'title' => 'Fees dashboard',
                'description' => 'Collections overview, defaulters, and the fee ledger.',
                'url' => FeesDashboardPage::getUrl(),
                'badge' => 'Start here',
                'tone' => 'primary',
            ];
        }

        if (BulkMiscChargePage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::bulkMiscCharges(),
                'description' => 'Add hostel, kit, or other extra charges to many students at once.',
                'url' => BulkMiscChargePage::getUrl(),
            ];
        }

        if (MiscChargeAdjustmentRequestsPage::canAccess()) {
            $cards[] = [
                'title' => 'Charge adjustments',
                'description' => 'Review discount and waive-off requests for misc charges.',
                'url' => MiscChargeAdjustmentRequestsPage::getUrl(),
            ];
        }

        if (PaymentCancellationRequestsPage::canAccess()) {
            $cards[] = [
                'title' => 'Payment cancellations',
                'description' => 'Approve staff requests to cancel the latest mistaken payment.',
                'url' => PaymentCancellationRequestsPage::getUrl(),
                'badge' => ($count = \App\Support\CrmNavBadges::paymentCancellationsPending()) > 0 ? (string) $count : null,
                'tone' => 'danger',
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.crm-hub')
                ->viewData([
                    'heading' => 'Fees desk',
                    'intro' => 'Institute fee operations. For one student, open their profile Fees tab.',
                    'cards' => $cards,
                    'footer' => '<strong class="text-gray-900 dark:text-white">Tip:</strong> Collect a single installment from the student profile — this hub is for overview and bulk work.',
                ]),
        ]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\FeeMiscChargeAdjustmentType;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\FeeMiscChargeAdjustmentRequest;
use App\Services\FeeDiscountHistoryService;
use App\Services\FeeMiscChargeAdjustmentService;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class MiscChargeAdjustmentRequestsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $navigationLabel = 'Charge adjustments';

    protected static ?string $title = 'Charge discount & waive requests';

    protected static ?string $slug = 'misc-charge-adjustments';

    protected static ?int $navigationSort = 28;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return false;
        }

        return Auth::user()?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public static function getNavigationBadge(): ?string
    {
        $count = CrmNavBadges::miscChargeAdjustmentsPending();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function approveRequest(int $requestId, ?string $reviewNotes = null): void
    {
        $request = FeeMiscChargeAdjustmentRequest::query()->findOrFail($requestId);
        app(FeeMiscChargeAdjustmentService::class)->approve($request, Auth::user(), $reviewNotes);

        Notification::make()
            ->title('Adjustment approved')
            ->body('The charge balance has been updated.')
            ->success()
            ->send();
    }

    public function rejectRequest(int $requestId, ?string $reviewNotes = null): void
    {
        $request = FeeMiscChargeAdjustmentRequest::query()->findOrFail($requestId);
        app(FeeMiscChargeAdjustmentService::class)->reject($request, Auth::user(), $reviewNotes);

        Notification::make()
            ->title('Request rejected')
            ->warning()
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, FeeMiscChargeAdjustmentRequest>
     */
    public function pendingRequests(FeeMiscChargeAdjustmentService $adjustments): \Illuminate\Support\Collection
    {
        return $adjustments->pendingRequests();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.misc-charge-adjustment-requests')
                ->viewData(fn (): array => [
                    'requests' => $this->pendingRequests(app(FeeMiscChargeAdjustmentService::class)),
                    'summary' => $this->discountSummary(app(FeeDiscountHistoryService::class)),
                    'history' => $this->discountHistory(app(FeeDiscountHistoryService::class)),
                    'feesLabel' => CrmMenuLabels::fees(),
                ]),
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Approve staff requests on additional charges and review the full record of tuition discounts and waive-offs.';
    }

    /**
     * @return array<string, int|float>
     */
    public function discountSummary(FeeDiscountHistoryService $history): array
    {
        return $history->summary();
    }

    /**
     * @return \Illuminate\Support\Collection<int, \App\Support\FeeDiscountHistoryItem>
     */
    public function discountHistory(FeeDiscountHistoryService $history): \Illuminate\Support\Collection
    {
        return $history->recent();
    }
}

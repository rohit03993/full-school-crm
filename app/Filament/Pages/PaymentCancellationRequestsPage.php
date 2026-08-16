<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\PaymentCancellationRequest;
use App\Services\PaymentCancellationService;
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

class PaymentCancellationRequestsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptRefund;

    protected static ?string $navigationLabel = 'Payment cancellations';

    protected static ?string $title = 'Payment cancellation requests';

    protected static ?string $slug = 'payment-cancellations';

    protected static ?int $navigationSort = 29;

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
        $count = CrmNavBadges::paymentCancellationsPending();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public function approveRequest(int $requestId, ?string $reviewNotes = null): void
    {
        $request = PaymentCancellationRequest::query()->findOrFail($requestId);
        app(PaymentCancellationService::class)->approve($request, Auth::user(), $reviewNotes);

        Notification::make()
            ->title('Payment cancelled')
            ->body('Balances, receipt, and ledger have been reversed. The receipt stays on file as Cancelled.')
            ->success()
            ->send();
    }

    public function rejectRequest(int $requestId, ?string $reviewNotes = null): void
    {
        $request = PaymentCancellationRequest::query()->findOrFail($requestId);
        app(PaymentCancellationService::class)->reject($request, Auth::user(), $reviewNotes);

        Notification::make()
            ->title('Request rejected')
            ->warning()
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, PaymentCancellationRequest>
     */
    public function pendingRequests(PaymentCancellationService $cancellations): \Illuminate\Support\Collection
    {
        return $cancellations->pendingRequests();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.payment-cancellation-requests')
                ->viewData(function (): array {
                    $cancellations = app(PaymentCancellationService::class);

                    return [
                        'requests' => $this->pendingRequests($cancellations),
                        'summary' => $cancellations->summary(),
                        'history' => $cancellations->recentHistory(),
                        'feesLabel' => CrmMenuLabels::fees(),
                    ];
                }),
        ]);
    }

    public function getSubheading(): ?string
    {
        return 'Staff request to cancel a mistaken payment. Only the latest active receipt can be cancelled; Super Admin must approve. Approved and rejected decisions stay in the history below.';
    }
}

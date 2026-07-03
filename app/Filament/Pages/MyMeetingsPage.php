<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmNavBadges;
use App\Support\CrmPagination;
use App\Services\MyLeadsService;
use App\Services\VisitMeetingAssignmentService;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class MyMeetingsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Assigned To Me';

    protected static ?string $title = 'Assigned To Me';

    protected static ?int $navigationSort = -199;

    protected static string|UnitEnum|null $navigationGroup = null;

    public function getSubheading(): ?string
    {
        return CrmHint::text('assigned.to.me');
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            return false;
        }

        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::LeadsCall)
            || $user->hasRole(RoleName::Staff->value);
    }

    public string $search = '';

    public string $statusFilter = 'open';

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $stats = [];

    /**
     * @var array{total: int, uncalled: int, called: int, due_call_followups: int}
     */
    public array $callStats = [];

    public function mount(): void
    {
        $staff = Auth::user();

        if ($staff) {
            $this->stats = app(VisitMeetingAssignmentService::class)->statsForStaff($staff);
            $this->callStats = app(MyLeadsService::class)->stats($staff);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    protected function refreshStats(): void
    {
        $staff = Auth::user();

        if ($staff) {
            $this->stats = app(VisitMeetingAssignmentService::class)->statsForStaff($staff);
            $this->callStats = app(MyLeadsService::class)->stats($staff);
        }
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $staff = Auth::user();

        if (! $staff) {
            return null;
        }

        $pending = CrmNavBadges::myMeetingsOpen($staff) + CrmNavBadges::myLeadsUncalled($staff);

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function content(Schema $schema): Schema
    {
        $staff = Auth::user();

        return $schema->components([
            View::make('filament.pages.partials.my-meetings')
                ->viewData(fn (): array => [
                    'meetings' => $staff
                        ? app(VisitMeetingAssignmentService::class)->paginateForStaff(
                            $staff,
                            $this->statusFilter,
                            $this->search,
                            page: $this->getPage(),
                        )
                        : null,
                    'search' => $this->search,
                    'statusFilter' => $this->statusFilter,
                    'stats' => $this->stats,
                    'callStats' => $this->callStats,
                ]),
        ]);
    }
}

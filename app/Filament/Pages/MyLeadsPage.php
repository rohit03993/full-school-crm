<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Services\MyLeadsService;
use App\Support\CrmHint;
use App\Support\CrmNavBadges;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class MyLeadsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $navigationLabel = 'Assigned to Call';

    protected static ?string $title = 'Assigned to Call';

    protected static ?int $navigationSort = 30;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_LEADS;

    public function getSubheading(): ?string
    {
        return CrmHint::text('assigned.to.call');
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            return false;
        }

        $user = Auth::user();

        if (! $user || $user->hasRole(RoleName::SuperAdmin->value)) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::LeadsViewAssigned)
            || $user->hasRole(RoleName::Staff->value);
    }

    public string $search = '';

    public string $calledFilter = 'all';

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{total: int, uncalled: int, called: int, due_call_followups: int}
     */
    public array $stats = [];

    public function mount(MyLeadsService $service): void
    {
        $staff = Auth::user();
        $this->stats = $service->stats($staff);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedCalledFilter(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    protected function refreshStats(): void
    {
        $staff = Auth::user();

        if ($staff) {
            $this->stats = app(MyLeadsService::class)->stats($staff);
        }
    }

    public static function getNavigationBadge(): ?string
    {
        $staff = Auth::user();

        if (! $staff) {
            return null;
        }

        $count = CrmNavBadges::myLeadsUncalled($staff);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    public function content(Schema $schema): Schema
    {
        $staff = Auth::user();

        return $schema->components([
            View::make('filament.pages.partials.my-leads')
                ->viewData(fn (): array => [
                    'leads' => $staff
                        ? app(MyLeadsService::class)->paginateLeads(
                            $staff,
                            $this->search,
                            $this->calledFilter,
                            page: $this->getPage(),
                        )
                        : null,
                    'stats' => $this->stats,
                    'search' => $this->search,
                    'calledFilter' => $this->calledFilter,
                ]),
        ]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\CampusVisitPurpose;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmPagination;
use App\Services\StudentCaseService;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class MyCasesPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = -198;

    protected static string|UnitEnum|null $navigationGroup = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::myCases();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::myCases();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('cases.my');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::CasesView);
    }

    public string $search = '';

    public string $statusFilter = 'open';

    public string $caseTypeFilter = '';

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $stats = [];

    public function mount(): void
    {
        $this->refreshStats();
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

    public function updatedCaseTypeFilter(): void
    {
        $this->resetPage();
    }

    protected function refreshStats(): void
    {
        $staff = Auth::user();

        if ($staff) {
            $this->stats = app(StudentCaseService::class)->statsForAssignee($staff);
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

        $open = CrmNavBadges::myCasesOpen($staff);

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function content(Schema $schema): Schema
    {
        $staff = Auth::user();

        return $schema->components([
            View::make('filament.pages.partials.my-cases')
                ->viewData(fn (): array => [
                    'cases' => $staff
                        ? app(StudentCaseService::class)->paginateForAssignee(
                            $staff,
                            $this->statusFilter,
                            $this->search,
                            $this->caseTypeFilter ?: null,
                            page: $this->getPage(),
                        )
                        : null,
                    'search' => $this->search,
                    'statusFilter' => $this->statusFilter,
                    'caseTypeFilter' => $this->caseTypeFilter,
                    'caseTypeOptions' => CampusVisitPurpose::options(),
                    'stats' => $this->stats,
                ]),
        ]);
    }
}

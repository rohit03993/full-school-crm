<?php

namespace App\Filament\Pages;

use App\Enums\CampusVisitPurpose;
use App\Enums\CrmPermission;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use App\Services\StudentCaseService;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class AllCasesPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 36;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::allCases();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::allCases();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('cases.all');
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        return CrmAccess::can($user, CrmPermission::CasesViewAll);
    }

    public string $search = '';

    public string $statusFilter = 'open';

    public string $caseTypeFilter = '';

    public string $assigneeFilter = '';

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $stats = [];

    public function mount(): void
    {
        $this->stats = app(StudentCaseService::class)->statsAll();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedCaseTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAssigneeFilter(): void
    {
        $this->resetPage();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public static function getNavigationBadge(): ?string
    {
        $open = CrmNavBadges::allCasesOpen();

        return $open > 0 ? (string) $open : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.all-cases')
                ->viewData(fn (): array => [
                    'cases' => app(StudentCaseService::class)->paginateAll(
                        $this->statusFilter,
                        $this->search,
                        $this->assigneeFilter !== '' ? (int) $this->assigneeFilter : null,
                        $this->caseTypeFilter ?: null,
                        page: $this->getPage(),
                    ),
                    'search' => $this->search,
                    'statusFilter' => $this->statusFilter,
                    'caseTypeFilter' => $this->caseTypeFilter,
                    'assigneeFilter' => $this->assigneeFilter,
                    'caseTypeOptions' => CampusVisitPurpose::options(),
                    'staffOptions' => StudentCaseService::activeStaffOptions(),
                    'stats' => $this->stats,
                ]),
        ]);
    }
}

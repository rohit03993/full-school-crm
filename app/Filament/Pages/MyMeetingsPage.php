<?php

namespace App\Filament\Pages;

use App\Enums\CampusVisitPurpose;
use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavBadges;
use App\Support\CrmPagination;
use App\Services\MyLeadsService;
use App\Services\StudentCaseService;
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

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = -199;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::myWork();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::myWork();
    }

    protected static string|UnitEnum|null $navigationGroup = null;

    public function getSubheading(): ?string
    {
        return match ($this->workTab) {
            'all_cases' => CrmHint::text('cases.all'),
            'my_cases' => CrmHint::text('cases.my'),
            default => $this->canAllCasesTab()
                ? CrmHint::text('work.supervisor.meetings')
                : CrmHint::text('assigned.to.me'),
        };
    }

    public static function canAccess(): bool
    {
        $user = Auth::user();

        if (! $user || ! $user->is_active) {
            return false;
        }

        $enquiriesOn = FeatureGate::enabled(LicenseFeature::Enquiries);
        $callsOn = FeatureGate::enabled(LicenseFeature::Calls);
        $casesOn = FeatureGate::enabled(LicenseFeature::Cases);

        if (! $enquiriesOn && ! $callsOn && ! $casesOn) {
            return false;
        }

        if ($casesOn && CrmAccess::can($user, CrmPermission::CasesView)) {
            return true;
        }

        if (($enquiriesOn || $callsOn) && (
            CrmAccess::can($user, CrmPermission::LeadsCall)
            || $user->hasRole(RoleName::Staff->value)
        )) {
            return true;
        }

        return false;
    }

    public string $workTab = 'meetings';

    public string $search = '';

    public string $statusFilter = 'open';

    public string $myCaseSearch = '';

    public string $myCaseStatusFilter = 'open';

    public string $myCaseTypeFilter = '';

    public string $allCaseSearch = '';

    public string $allCaseStatusFilter = 'open';

    public string $allCaseTypeFilter = '';

    public string $allCaseAssigneeFilter = '';

    public int $perPage = CrmPagination::PER_PAGE;

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $stats = [];

    /**
     * @var array{total: int, uncalled: int, called: int, due_call_followups: int}
     */
    public array $callStats = [];

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $caseStats = [];

    /**
     * @var array{open: int, closed: int, total: int}
     */
    public array $allCaseStats = [];

    public function mount(?string $tab = null): void
    {
        $requestedTab = $tab ?: request()->string('tab')->toString();

        if ($requestedTab === 'all_cases' && $this->canAllCasesTab()) {
            $this->workTab = 'all_cases';
        } elseif ($requestedTab === 'my_cases' && $this->canMyCasesTab()) {
            $this->workTab = 'my_cases';
        } elseif ($requestedTab === 'meetings' && $this->canMeetingsTab()) {
            $this->workTab = 'meetings';
        } elseif ($this->canAllCasesTab()) {
            $this->workTab = 'all_cases';
        } elseif ($this->canMyCasesTab()) {
            $this->workTab = 'my_cases';
        } elseif ($this->canMeetingsTab()) {
            $this->workTab = 'meetings';
        }

        $this->refreshStats();
    }

    public function switchWorkTab(string $tab): void
    {
        if ($tab === 'my_cases' && ! $this->canMyCasesTab()) {
            return;
        }

        if ($tab === 'all_cases' && ! $this->canAllCasesTab()) {
            return;
        }

        if ($tab === 'meetings' && ! $this->canMeetingsTab()) {
            return;
        }

        if (! in_array($tab, ['meetings', 'my_cases', 'all_cases'], true)) {
            return;
        }

        $this->workTab = $tab;
        $this->resetPage();
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

    public function updatedMyCaseSearch(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedMyCaseStatusFilter(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedMyCaseTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAllCaseSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAllCaseStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAllCaseTypeFilter(): void
    {
        $this->resetPage();
    }

    public function updatedAllCaseAssigneeFilter(): void
    {
        $this->resetPage();
    }

    protected function refreshStats(): void
    {
        $staff = Auth::user();

        if (! $staff) {
            return;
        }

        if ($this->canMeetingsTab()) {
            $this->stats = app(VisitMeetingAssignmentService::class)->statsForStaff($staff);
        }

        if (FeatureGate::enabled(LicenseFeature::Calls) || FeatureGate::enabled(LicenseFeature::Enquiries)) {
            $this->callStats = app(MyLeadsService::class)->stats($staff);
        }

        if ($this->canMyCasesTab()) {
            $this->caseStats = app(StudentCaseService::class)->statsForAssignee($staff);
        }

        if ($this->canAllCasesTab()) {
            $this->allCaseStats = app(StudentCaseService::class)->statsAll();
        }
    }

    public function canMyCasesTab(): bool
    {
        $user = Auth::user();

        return $user
            && FeatureGate::enabled(LicenseFeature::Cases)
            && CrmAccess::can($user, CrmPermission::CasesView);
    }

    public function canAllCasesTab(): bool
    {
        $user = Auth::user();

        // Institute-wide case list is Super Admin only — staff use My meetings / My cases.
        return $user
            && FeatureGate::enabled(LicenseFeature::Cases)
            && $user->hasRole(RoleName::SuperAdmin->value);
    }

    public function canMeetingsTab(): bool
    {
        return FeatureGate::enabled(LicenseFeature::Enquiries);
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

        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            $openCases = CrmNavBadges::allCasesOpen();

            return $openCases > 0 ? (string) $openCases : null;
        }

        $pending = CrmNavBadges::myMeetingsOpen($staff)
            + CrmNavBadges::myLeadsUncalled($staff)
            + CrmNavBadges::myCasesOpen($staff);

        return $pending > 0 ? (string) $pending : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function content(Schema $schema): Schema
    {
        $staff = Auth::user();
        $caseService = app(StudentCaseService::class);

        return $schema->components([
            View::make('filament.pages.partials.my-work')
                ->viewData(fn (): array => [
                    'workTab' => $this->workTab,
                    'canMyCasesTab' => $this->canMyCasesTab(),
                    'canAllCasesTab' => $this->canAllCasesTab(),
                    'canMeetingsTab' => $this->canMeetingsTab(),
                    'meetings' => $staff && $this->canMeetingsTab() && $this->workTab === 'meetings'
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
                    'caseStats' => $this->caseStats,
                    'myCases' => $staff && $this->canMyCasesTab()
                        ? $caseService->paginateForAssignee(
                            $staff,
                            $this->myCaseStatusFilter,
                            $this->myCaseSearch,
                            $this->myCaseTypeFilter ?: null,
                            page: $this->getPage(),
                        )
                        : null,
                    'myCaseSearch' => $this->myCaseSearch,
                    'myCaseStatusFilter' => $this->myCaseStatusFilter,
                    'myCaseTypeFilter' => $this->myCaseTypeFilter,
                    'allCases' => $this->canAllCasesTab()
                        ? $caseService->paginateAll(
                            $this->allCaseStatusFilter,
                            $this->allCaseSearch,
                            $this->allCaseAssigneeFilter !== '' ? (int) $this->allCaseAssigneeFilter : null,
                            $this->allCaseTypeFilter ?: null,
                            page: $this->getPage(),
                        )
                        : null,
                    'allCaseSearch' => $this->allCaseSearch,
                    'allCaseStatusFilter' => $this->allCaseStatusFilter,
                    'allCaseTypeFilter' => $this->allCaseTypeFilter,
                    'allCaseAssigneeFilter' => $this->allCaseAssigneeFilter,
                    'allCaseStats' => $this->allCaseStats,
                    'caseTypeOptions' => CampusVisitPurpose::options(),
                    'staffOptions' => StudentCaseService::activeStaffOptions(),
                ]),
        ]);
    }
}

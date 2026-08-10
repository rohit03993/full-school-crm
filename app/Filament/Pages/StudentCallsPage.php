<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\StudentCallsService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class StudentCallsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPhone;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?string $slug = 'student-calls';

    /** Just below All students (sort 10). */
    protected static ?int $navigationSort = 11;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::studentCalls();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::studentCalls();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('students.calls');
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::StudentsView);
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canAccess();
    }

    public string $dateFrom = '';

    public string $dateTo = '';

    public string $purposeFilter = '';

    public string $search = '';

    public string $staffFilter = '';

    /**
     * @var array{total: int}
     */
    public array $stats = ['total' => 0];

    public function mount(StudentCallsService $calls): void
    {
        $this->applyDefaultDates();
        $this->refreshStats($calls);
    }

    public function updatedDateFrom(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedPurposeFilter(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function updatedStaffFilter(): void
    {
        $this->resetPage();
        $this->refreshStats();
    }

    public function resetFilters(): void
    {
        $this->purposeFilter = '';
        $this->search = '';
        $this->staffFilter = '';
        $this->applyDefaultDates();
        $this->resetPage();
        $this->refreshStats();
    }

    protected function applyDefaultDates(): void
    {
        $this->dateFrom = today()->subDays(29)->toDateString();
        $this->dateTo = today()->toDateString();
    }

    protected function refreshStats(?StudentCallsService $calls = null): void
    {
        $calls ??= app(StudentCallsService::class);
        $this->stats = $calls->summary($this->filters($calls));
    }

    /**
     * @return array{from: string, to: string, purpose: ?string, search: string, staff_user_id: ?int}
     */
    protected function filters(StudentCallsService $calls): array
    {
        return $calls->normalizeFilters([
            'from' => $this->dateFrom,
            'to' => $this->dateTo,
            'purpose' => $this->purposeFilter,
            'search' => $this->search,
            'staff_user_id' => $this->staffFilter !== '' ? (int) $this->staffFilter : null,
        ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.student-calls')
                ->viewData(function (): array {
                    $service = app(StudentCallsService::class);
                    $filters = $this->filters($service);

                    $this->dateFrom = $filters['from'];
                    $this->dateTo = $filters['to'];

                    return [
                        'calls' => $service->paginate($filters, page: $this->getPage()),
                        'stats' => $this->stats,
                        'purposeOptions' => $service->purposeOptions(),
                        'staffOptions' => $service->staffOptions(),
                    ];
                }),
        ]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Models\StudentConductRecord;
use App\Services\StudentConductService;
use App\Support\ConductTypeOptions;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\CrmPagination;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use UnitEnum;

class StudentReportsPage extends Page
{
    use WithPagination;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static ?string $slug = 'student-reports';

    protected static ?int $navigationSort = 34;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_STUDENTS;

    public string $search = '';

    public string $fineFilter = '';

    public string $typeFilter = '';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::studentReports();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::studentReports();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('students.reports');
    }

    public static function getNavigationTooltip(): ?string
    {
        return CrmHint::navigationTooltip('students.reports');
    }

    public static function canAccess(): bool
    {
        return app(StudentConductService::class)->canRecord(Auth::user());
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canAccess()) {
            return null;
        }

        $total = StudentConductRecord::query()->count();

        return $total > 0 ? (string) $total : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'danger';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedFineFilter(): void
    {
        $this->resetPage();
    }

    public function updatedTypeFilter(): void
    {
        $this->resetPage();
    }

    public function content(Schema $schema): Schema
    {
        $service = app(StudentConductService::class);

        return $schema->components([
            View::make('filament.pages.partials.student-reports')
                ->viewData(fn (): array => [
                    'reports' => $service->paginate(
                        $this->search,
                        $this->fineFilter,
                        $this->typeFilter,
                        CrmPagination::PER_PAGE,
                    ),
                    'stats' => $service->stats(),
                    'typeOptions' => ConductTypeOptions::formOptions(),
                ]),
        ]);
    }
}

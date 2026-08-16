<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\BatchOverviewWidget;
use App\Filament\Widgets\CrmFinanceStatsWidget;
use App\Filament\Widgets\CrmLeadStatsWidget;
use App\Filament\Widgets\CourseAdmissionsChartWidget;
use App\Filament\Widgets\DashboardAnalyticsWidget;
use App\Filament\Widgets\DashboardAttentionWidget;
use App\Filament\Widgets\DashboardHeroWidget;
use App\Filament\Widgets\DashboardTodayPulseWidget;
use App\Filament\Widgets\LicenseStatusWidget;
use App\Filament\Widgets\LeadSourceChartWidget;
use App\Filament\Widgets\MonthlyAdmissionsChartWidget;
use App\Filament\Widgets\MonthlyFeeCollectionChartWidget;
use App\Filament\Widgets\PendingAdmissionsWidget;
use App\Filament\Widgets\RecentEnquiriesWidget;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\DashboardFilters;
use App\Support\InstituteProfile;
use App\Support\InstituteTerminology;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static ?string $navigationLabel = null;

    protected static ?int $navigationSort = -200;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::dashboard();
    }

    public function getTitle(): string | Htmlable
    {
        return '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return CrmHint::text('dashboard');
    }

    /**
     * @return int | array<string, ?int>
     */
    public function getColumns(): int | array
    {
        return [
            'default' => 1,
            'lg' => 2,
        ];
    }

    /**
     * Filament renders this form inside its own 2/3/4-column grid, so the toolbar
     * claims the full width and lays its own controls out inside.
     */
    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->extraAttributes(['class' => 'crm-dash-filters'])
                ->columnSpanFull()
                ->columns(1)
                ->schema([
                    ToggleButtons::make('range')
                        ->hiddenLabel()
                        ->options([
                            DashboardFilters::RANGE_TODAY => 'Today',
                            DashboardFilters::RANGE_WEEK => '7 days',
                            DashboardFilters::RANGE_MONTH => 'This month',
                            DashboardFilters::RANGE_QUARTER => '3 months',
                            DashboardFilters::RANGE_SESSION => 'Session',
                            DashboardFilters::RANGE_CUSTOM => 'Custom',
                        ])
                        ->default(DashboardFilters::RANGE_MONTH)
                        ->inline()
                        ->grouped()
                        ->live(),
                    Grid::make(['default' => 1, 'sm' => 2, 'lg' => 4])
                        ->schema([
                            Select::make('academic_session_id')
                                ->label('Session')
                                ->options(fn (): array => AcademicSession::query()
                                    ->orderByDesc('is_current')
                                    ->orderByDesc('starts_on')
                                    ->get()
                                    ->mapWithKeys(fn (AcademicSession $session): array => [
                                        $session->id => $session->selectLabel(),
                                    ])
                                    ->all())
                                ->default(fn (): ?int => AcademicSession::current()?->id)
                                ->placeholder('All sessions')
                                ->native(false),
                            Select::make('course_id')
                                ->label(InstituteTerminology::label('course'))
                                ->options(fn (): array => InstituteProfile::activeCourseOptions())
                                ->placeholder('All')
                                ->searchable()
                                ->native(false)
                                ->live(),
                            Select::make('batch_id')
                                ->label(InstituteTerminology::label('batch'))
                                ->options(fn (Get $get): array => Batch::query()
                                    ->when(filled($get('course_id')), fn ($query) => $query->where('course_id', (int) $get('course_id')))
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->placeholder('All')
                                ->searchable()
                                ->native(false),
                            DatePicker::make('from')
                                ->label('From')
                                ->native(false)
                                ->maxDate(now())
                                ->default(now()->startOfMonth())
                                ->visible(fn (Get $get): bool => $get('range') === DashboardFilters::RANGE_CUSTOM),
                            DatePicker::make('to')
                                ->label('To')
                                ->native(false)
                                ->maxDate(now())
                                ->default(now())
                                ->visible(fn (Get $get): bool => $get('range') === DashboardFilters::RANGE_CUSTOM),
                        ]),
                ]),
        ]);
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('searchStudent')
                ->label('Search Student')
                ->icon(Heroicon::OutlinedMagnifyingGlass)
                ->url(StudentSearchPage::getUrl())
                ->color('primary')
                ->visible(fn (): bool => StudentSearchPage::canAccess()),
        ];
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            LicenseStatusWidget::class,
            DashboardHeroWidget::class,
            DashboardAttentionWidget::class,
            DashboardTodayPulseWidget::class,
            DashboardAnalyticsWidget::class,
            CrmFinanceStatsWidget::class,
            CrmLeadStatsWidget::class,
            BatchOverviewWidget::class,
            RecentEnquiriesWidget::class,
            PendingAdmissionsWidget::class,
            MonthlyFeeCollectionChartWidget::class,
            MonthlyAdmissionsChartWidget::class,
            LeadSourceChartWidget::class,
            CourseAdmissionsChartWidget::class,
        ];
    }
}

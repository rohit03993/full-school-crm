<?php

namespace App\Filament\Pages;

use App\Models\ActivityType;
use App\Enums\CrmPermission;
use App\Enums\LeadSource;
use App\Enums\LicenseFeature;
use App\Enums\ReportType;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Policies\ReportPolicy;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use App\Exports\ReportExport;
use App\Support\ReportCsvExporter;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Livewire\WithPagination;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ReportsPage extends Page
{
    use WithPagination;

    public const PREVIEW_PER_PAGE = 20;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::reports();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::reports();
    }

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_REPORTS;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Reports)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::ReportsView);
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('reports');
    }

    public ?string $reportType = ReportType::Enquiries->value;

    /**
     * @var array<string, mixed>
     */
    public array $filters = [];

    /**
     * @var ?array{
     *     title: string,
     *     columns: array<int, string>,
     *     rows: array<int, array<int, string|int|float|null>>,
     *     generated_at: string
     * }
     */
    public ?array $report = null;

    public function mount(): void
    {
        $this->filters = $this->defaultFiltersForSelectedReport();
        $this->runReport(app(ReportService::class), notify: false);
    }

    public function runReport(ReportService $reports, bool $notify = true): void
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        if (! $type) {
            return;
        }

        $this->report = $reports->generate($type, $this->normalizedFilters());
        $this->resetPage();

        if ($notify) {
            Notification::make()
                ->title('Report updated')
                ->body(count($this->report['rows']).' row(s) · '.$this->report['title'])
                ->success()
                ->send();
        }
    }

    public function clearFilters(): void
    {
        $this->filters = $this->defaultFiltersForSelectedReport();
        $this->runReport(app(ReportService::class), notify: false);
    }

    public function exportCsv(ReportService $reports): StreamedResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $filename = Str::slug($type->value).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ReportCsvExporter::export($this->report ?? $reports->generate($type, $this->normalizedFilters())),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function exportExcel(ReportService $reports): BinaryFileResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $data = $this->report ?? $reports->generate($type, $this->normalizedFilters());
        $filename = Str::slug($type->value).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ReportExport($data), $filename);
    }

    public function exportPdf(ReportService $reports, ReportPdfService $pdf): StreamedResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $data = $this->report ?? $reports->generate($type, $this->normalizedFilters());
        $filename = Str::slug($type->value).'-'.now()->format('Y-m-d').'.pdf';

        return response()->streamDownload(
            fn () => print $pdf->generate($data),
            $filename,
            ['Content-Type' => 'application/pdf'],
        );
    }

    protected function resolveAuthorizedReport(ReportService $reports): ReportType
    {
        $type = ReportType::from((string) $this->reportType);
        $policy = app(ReportPolicy::class);

        abort_unless($policy->export(Auth::user(), $type), 403);

        if (! $this->report) {
            $this->report = $reports->generate($type, $this->normalizedFilters());
        }

        return $type;
    }

    /**
     * @return array<string, mixed>
     */
    protected function normalizedFilters(): array
    {
        $filters = $this->filters;

        foreach (['course_id', 'batch_id', 'student_id', 'user_id', 'activity_type_id', 'lead_source'] as $key) {
            if (! array_key_exists($key, $filters) || $filters[$key] === '' || $filters[$key] === null) {
                unset($filters[$key]);
            }
        }

        foreach (['min_days_open', 'min_days_since_contact'] as $key) {
            if (! array_key_exists($key, $filters) || $filters[$key] === '' || $filters[$key] === null) {
                unset($filters[$key]);
            }
        }

        return $filters;
    }

    /**
     * @return LengthAwarePaginator<int, array<int, string|int|float|null>>|null
     */
    protected function paginatedReportRows(): ?LengthAwarePaginator
    {
        if ($this->report === null) {
            return null;
        }

        $rows = $this->report['rows'];
        $total = count($rows);
        $page = $this->getPage();
        $perPage = self::PREVIEW_PER_PAGE;
        $items = array_slice($rows, ($page - 1) * $perPage, $perPage);

        return new Paginator(
            $items,
            $total,
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function defaultFiltersForSelectedReport(): array
    {
        $type = ReportType::tryFrom((string) $this->reportType) ?? ReportType::Enquiries;

        return self::defaultFiltersFor($type);
    }

    /**
     * @return array<string, mixed>
     */
    public static function defaultFiltersFor(ReportType $type): array
    {
        $filters = [];

        if ($type->usesDateRange()) {
            $filters['date_from'] = now()->startOfMonth()->toDateString();
            $filters['date_to'] = now()->toDateString();
        }

        if ($type === ReportType::LowAttendanceAlert) {
            $filters['max_percentage'] = 75;
        }

        return $filters;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->columns([
                'default' => 1,
                'md' => 2,
                'xl' => 3,
            ])
            ->components([
                DatePicker::make('date_from')
                    ->label('From')
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('date_from')),
                DatePicker::make('date_to')
                    ->label('To')
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('date_to')),
                Select::make('course_id')
                    ->label(fn (): string => $this->reportType === ReportType::LeadAging->value
                        ? 'Course interested in'
                        : 'Course')
                    ->options(fn (): array => InstituteProfile::activeCourseOptions())
                    ->searchable()
                    ->placeholder('All courses')
                    ->nullable()
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('course_id')),
                Select::make('batch_id')
                    ->label('Batch')
                    ->options(fn (): array => Batch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All batches')
                    ->nullable()
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('batch_id')),
                Select::make('student_id')
                    ->label('Student')
                    ->searchable()
                    ->placeholder('All students')
                    ->nullable()
                    ->getSearchResultsUsing(fn (string $search): array => Student::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Student::query()->find($value)?->name)
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('student_id')),
                Select::make('lead_source')
                    ->label('Lead source')
                    ->options(collect(LeadSource::cases())->mapWithKeys(
                        fn (LeadSource $source) => [$source->value => $source->label()],
                    ))
                    ->placeholder('All sources')
                    ->nullable()
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('lead_source')),
                TextInput::make('min_days_open')
                    ->label('Minimum days open')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Any age')
                    ->helperText('Optional. Example: 7 = at least one week old.')
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('min_days_open')),
                TextInput::make('min_days_since_contact')
                    ->label('Minimum days since last contact')
                    ->numeric()
                    ->minValue(0)
                    ->placeholder('Any contact age')
                    ->helperText('Optional. Example: 7 = no call or visit in 7+ days.')
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('min_days_since_contact')),
                Select::make('user_id')
                    ->label(fn (): string => $this->reportType === ReportType::LeadAging->value
                        ? 'Assigned counsellor'
                        : 'Staff')
                    ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->placeholder('All staff')
                    ->nullable()
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('user_id')),
                Select::make('activity_type_id')
                    ->label('Exam type')
                    ->options(fn (): array => ActivityType::query()->enabled()->ordered()->pluck('name', 'id')->all())
                    ->placeholder('All exam types')
                    ->nullable()
                    ->native(false)
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('activity_type_id')),
                TextInput::make('max_percentage')
                    ->label('Attendance below %')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(100)
                    ->helperText('Students under this percentage are flagged (default 75).')
                    ->visible(fn (): bool => $this->selectedReportShowsFilter('max_percentage')),
            ]);
    }

    protected function selectedReportShowsFilter(string $key): bool
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        return $type?->showsFilter($key) ?? false;
    }

    protected function selectedReportHasOptionalFilters(): bool
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        if (! $type) {
            return false;
        }

        $optionalKeys = array_diff($type->filterKeys(), ['date_from', 'date_to']);

        return $optionalKeys !== [];
    }

    /**
     * @return array<string, string>
     */
    protected function availableReportOptions(): array
    {
        $user = Auth::user();
        $policy = app(ReportPolicy::class);

        return collect(ReportType::cases())
            ->filter(function (ReportType $type) use ($user, $policy): bool {
                $feature = $type->requiredLicenseFeature();

                if ($feature !== null && ! FeatureGate::enabled($feature)) {
                    return false;
                }

                return $policy->export($user, $type);
            })
            ->mapWithKeys(fn (ReportType $type): array => [$type->value => $type->label()])
            ->all();
    }

    protected function selectedReportUsesDateRange(): bool
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        return $type?->usesDateRange() ?? true;
    }

    protected function selectedReportHasFilters(): bool
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        return $type !== null && $type->filterKeys() !== [];
    }

    protected function filtersSectionDescription(): string
    {
        if ($this->selectedReportUsesDateRange() && ! $this->selectedReportHasOptionalFilters()) {
            return 'Default is this month. Change the dates and tap Apply to refresh.';
        }

        return 'Optional fields are blank by default (include all). Change only what you need, then tap Apply.';
    }

    public function getFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('filtersForm')])
            ->id('reportFilters')
            ->livewireSubmitHandler('runReport')
            ->footer([
                Actions::make([
                    Action::make('clearFilters')
                        ->label('Clear filters')
                        ->color('gray')
                        ->icon(Heroicon::OutlinedXMark)
                        ->action('clearFilters')
                        ->visible(fn (): bool => $this->selectedReportHasOptionalFilters()),
                    Action::make('runReport')
                        ->label('Apply')
                        ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                        ->submit('runReport'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report')
                ->description('Pick a report — results load automatically. Financial exports remain Super Admin only.')
                ->schema([
                    Select::make('reportType')
                        ->label('Report type')
                        ->options($this->availableReportOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(function (): void {
                            $this->report = null;
                            $this->filters = $this->defaultFiltersForSelectedReport();
                            $this->resetPage();
                            $this->runReport(app(ReportService::class), notify: false);
                        })
                        ->native(false)
                        ->columnSpanFull(),
                    Placeholder::make('report_filter_hint')
                        ->label('')
                        ->content(fn (): string => ReportType::tryFrom((string) $this->reportType)?->filterHint() ?? '')
                        ->visible(fn (): bool => filled(ReportType::tryFrom((string) $this->reportType)?->filterHint()))
                        ->columnSpanFull(),
                ])
                ->columns(1)
                ->compact(),
            Section::make('Filters')
                ->description(fn (): string => $this->filtersSectionDescription())
                ->schema([
                    $this->getFiltersFormComponent(),
                ])
                ->compact()
                ->collapsible()
                ->visible(fn (): bool => $this->selectedReportHasFilters()),
            Section::make('Results')
                ->schema([
                    View::make('filament.pages.partials.reports-preview')
                        ->viewData(fn (): array => [
                            'report' => $this->report,
                            'paginatedRows' => $this->paginatedReportRows(),
                            'canExport' => filled($this->reportType) && app(ReportPolicy::class)->export(
                                Auth::user(),
                                ReportType::from((string) $this->reportType),
                            ),
                        ]),
                ])
                ->compact(),
        ]);
    }
}

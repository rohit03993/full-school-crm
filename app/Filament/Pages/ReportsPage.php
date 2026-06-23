<?php

namespace App\Filament\Pages;

use App\Models\ActivityType;
use App\Enums\CrmPermission;
use App\Enums\LeadSource;
use App\Support\CrmAccess;
use App\Enums\ReportType;
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
use App\Support\CrmNavigation;
use App\Support\InstituteProfile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
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
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ReportsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?string $navigationLabel = 'Reports';

    protected static ?string $title = 'Reports';

    protected static ?int $navigationSort = 10;

    protected static string | UnitEnum | null $navigationGroup = CrmNavigation::GROUP_REPORTS;

    public static function canAccess(): bool
    {
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
        $this->filters = [
            'date_from' => now()->startOfMonth()->toDateString(),
            'date_to' => now()->toDateString(),
        ];
    }

    public function runReport(ReportService $reports): void
    {
        $type = ReportType::tryFrom((string) $this->reportType);

        if (! $type) {
            return;
        }

        $this->report = $reports->generate($type, $this->filters);

        Notification::make()
            ->title('Report generated')
            ->body(count($this->report['rows']).' row(s) · '.$this->report['title'])
            ->success()
            ->send();
    }

    public function exportCsv(ReportService $reports): StreamedResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $filename = Str::slug($type->value).'-'.now()->format('Y-m-d').'.csv';

        return response()->streamDownload(
            fn () => print ReportCsvExporter::export($this->report ?? $reports->generate($type, $this->filters)),
            $filename,
            ['Content-Type' => 'text/csv'],
        );
    }

    public function exportExcel(ReportService $reports): BinaryFileResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $data = $this->report ?? $reports->generate($type, $this->filters);
        $filename = Str::slug($type->value).'-'.now()->format('Y-m-d').'.xlsx';

        return Excel::download(new ReportExport($data), $filename);
    }

    public function exportPdf(ReportService $reports, ReportPdfService $pdf): StreamedResponse
    {
        $type = $this->resolveAuthorizedReport($reports);
        $data = $this->report ?? $reports->generate($type, $this->filters);
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
            $this->report = $reports->generate($type, $this->filters);
        }

        return $type;
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema
            ->statePath('filters')
            ->components([
                DatePicker::make('date_from')->label('From')->native(false),
                DatePicker::make('date_to')->label('To')->native(false),
                Select::make('course_id')
                    ->label('Course')
                    ->options(fn (): array => InstituteProfile::activeCourseOptions())
                    ->searchable()
                    ->native(false),
                Select::make('batch_id')
                    ->label('Batch')
                    ->options(fn (): array => Batch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->native(false),
                Select::make('student_id')
                    ->label('Student')
                    ->searchable()
                    ->getSearchResultsUsing(fn (string $search): array => Student::query()
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('mobile', 'like', "%{$search}%")
                        ->limit(20)
                        ->pluck('name', 'id')
                        ->all())
                    ->getOptionLabelUsing(fn ($value): ?string => Student::query()->find($value)?->name)
                    ->native(false),
                Select::make('lead_source')
                    ->label('Lead source')
                    ->options(collect(LeadSource::cases())->mapWithKeys(
                        fn (LeadSource $source) => [$source->value => $source->label()],
                    ))
                    ->native(false),
                Select::make('user_id')
                    ->label('Staff')
                    ->options(fn (): array => User::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->native(false),
                Select::make('activity_type_id')
                    ->label('Exam type')
                    ->options(fn (): array => ActivityType::query()->enabled()->ordered()->pluck('name', 'id')->all())
                    ->native(false),
            ]);
    }

    /**
     * @return array<string, string>
     */
    protected function availableReportOptions(): array
    {
        $user = Auth::user();
        $policy = app(ReportPolicy::class);

        return collect(ReportType::cases())
            ->filter(fn (ReportType $type): bool => $policy->export($user, $type))
            ->mapWithKeys(fn (ReportType $type): array => [$type->value => $type->label()])
            ->all();
    }

    public function getFiltersFormComponent(): Component
    {
        return Form::make([EmbeddedSchema::make('filtersForm')])
            ->id('reportFilters')
            ->livewireSubmitHandler('runReport')
            ->footer([
                Actions::make([
                    \Filament\Actions\Action::make('runReport')
                        ->label('Generate Report')
                        ->icon(Heroicon::OutlinedPlay)
                        ->submit('runReport'),
                ])
                    ->alignment(Alignment::Start)
                    ->fullWidth(),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Report filters')
                ->description('Staff can export operational reports. Financial exports are Super Admin only.')
                ->schema([
                    Select::make('reportType')
                        ->label('Report')
                        ->options($this->availableReportOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn () => $this->report = null)
                        ->native(false),
                    $this->getFiltersFormComponent(),
                ])
                ->compact(),
            View::make('filament.pages.partials.reports-preview')
                ->viewData(fn (): array => [
                    'report' => $this->report,
                    'canExport' => filled($this->reportType) && app(ReportPolicy::class)->export(
                        Auth::user(),
                        ReportType::from((string) $this->reportType),
                    ),
                ]),
        ]);
    }
}

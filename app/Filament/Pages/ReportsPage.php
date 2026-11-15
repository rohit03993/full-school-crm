<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\ReportType;
use App\Exports\ReportExport;
use App\Models\Batch;
use App\Policies\ReportPolicy;
use App\Services\ReportPdfService;
use App\Services\ReportService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use App\Support\InstituteProfile;
use App\Support\ReportCsvExporter;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use UnitEnum;

class ReportsPage extends Page
{
    use WithPagination;

    public const PREVIEW_PER_PAGE = 20;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentChartBar;

    protected static ?int $navigationSort = 10;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_REPORTS;

    protected string $view = 'filament.pages.attendance-report';

    public string $datePreset = 'this_month';

    public string $attendanceView = 'student';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public ?string $courseId = null;

    public ?string $batchId = null;

    public string $studentSearch = '';

    public ?string $maxPercentage = null;

    /**
     * @var ?array<string, mixed>
     */
    public ?array $report = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::reports();
    }

    public function getTitle(): string
    {
        return 'Attendance report';
    }

    public function getSubheading(): ?string
    {
        return 'Class wise or student wise. The lowest attendance stays at the top.';
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Reports)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::ReportsView);
    }

    public function mount(): void
    {
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->loadReport();
    }

    public function applyPreset(string $preset): void
    {
        if (! in_array($preset, ['this_month', 'last_month', 'custom'], true)) {
            return;
        }

        $this->datePreset = $preset;

        if ($preset === 'this_month') {
            $this->dateFrom = now()->startOfMonth()->toDateString();
            $this->dateTo = now()->toDateString();
        }

        if ($preset === 'last_month') {
            $this->dateFrom = now()->subMonthNoOverflow()->startOfMonth()->toDateString();
            $this->dateTo = now()->subMonthNoOverflow()->endOfMonth()->toDateString();
        }

        if ($preset !== 'custom') {
            $this->loadReport();
        }
    }

    public function markCustomDates(): void
    {
        $this->datePreset = 'custom';
    }

    public function setAttendanceView(string $view): void
    {
        $this->attendanceView = $view === 'class' ? 'class' : 'student';
        $this->loadReport();
    }

    public function updatedCourseId(): void
    {
        $this->batchId = null;
    }

    public function applyReport(): void
    {
        $this->loadReport(notify: true);
    }

    public function clearReportFilters(): void
    {
        $this->datePreset = 'this_month';
        $this->attendanceView = 'student';
        $this->dateFrom = now()->startOfMonth()->toDateString();
        $this->dateTo = now()->toDateString();
        $this->courseId = null;
        $this->batchId = null;
        $this->studentSearch = '';
        $this->maxPercentage = null;
        $this->loadReport();
    }

    public function exportCsv(ReportService $reports): StreamedResponse
    {
        $this->authorizeExport();
        $data = $this->report ?? $reports->generate(ReportType::ClassStudentAttendance, $this->filtersForReport());

        return response()->streamDownload(
            fn () => print ReportCsvExporter::export($data),
            'class-student-attendance-'.now()->format('Y-m-d').'.csv',
            ['Content-Type' => 'text/csv'],
        );
    }

    public function exportExcel(ReportService $reports): BinaryFileResponse
    {
        $this->authorizeExport();
        $data = $this->report ?? $reports->generate(ReportType::ClassStudentAttendance, $this->filtersForReport());

        return Excel::download(new ReportExport($data), 'class-student-attendance-'.now()->format('Y-m-d').'.xlsx');
    }

    public function exportPdf(ReportService $reports, ReportPdfService $pdf): StreamedResponse
    {
        $this->authorizeExport();
        $data = $this->report ?? $reports->generate(ReportType::ClassStudentAttendance, $this->filtersForReport());

        return response()->streamDownload(
            fn () => print $pdf->generate($data),
            'class-student-attendance-'.now()->format('Y-m-d').'.pdf',
            ['Content-Type' => 'application/pdf'],
        );
    }

    public function canExportReport(): bool
    {
        $user = Auth::user();

        return $user !== null && app(ReportPolicy::class)->export($user, ReportType::ClassStudentAttendance);
    }

    /**
     * @return array<int|string, string>
     */
    public function courseOptions(): array
    {
        return InstituteProfile::activeCourseOptions();
    }

    /**
     * @return array<int|string, string>
     */
    public function batchOptions(): array
    {
        $query = Batch::query()->orderBy('name');

        if (filled($this->courseId)) {
            $query->where('course_id', (int) $this->courseId);
        }

        return $query->pluck('name', 'id')->all();
    }

    /**
     * @return array{count: int, students: int, average: ?float, lowest: ?float, under: int}
     */
    public function attendanceSummary(): array
    {
        $rows = $this->report['rows'] ?? [];
        $index = $this->report['percent_column'] ?? null;

        if (! is_int($index) || $rows === []) {
            return ['count' => 0, 'students' => 0, 'average' => null, 'lowest' => null, 'under' => 0];
        }

        $percents = array_map(fn (array $row): float => (float) ($row[$index] ?? 0), $rows);
        $students = $this->attendanceView === 'class'
            ? (int) array_sum(array_map(fn (array $row): int => (int) ($row[1] ?? 0), $rows))
            : count($rows);

        return [
            'count' => count($rows),
            'students' => $students,
            'average' => round(array_sum($percents) / count($percents), 1),
            'lowest' => min($percents),
            'under' => count(array_filter($percents, fn (float $value): bool => $value < 75)),
        ];
    }

    public function reportRows(): LengthAwarePaginator
    {
        $rows = $this->report['rows'] ?? [];
        $page = $this->getPage();
        $perPage = self::PREVIEW_PER_PAGE;

        return new Paginator(
            array_slice($rows, ($page - 1) * $perPage, $perPage),
            count($rows),
            $perPage,
            $page,
            ['path' => request()->url(), 'pageName' => 'page'],
        );
    }

    public function percentTone(mixed $value): ?string
    {
        if (! is_numeric($value)) {
            return null;
        }

        $number = (float) $value;

        if ($number < 75) {
            return 'low';
        }

        if ($number < 90) {
            return 'mid';
        }

        return 'high';
    }

    protected function loadReport(bool $notify = false): void
    {
        $this->report = app(ReportService::class)->generate(
            ReportType::ClassStudentAttendance,
            $this->filtersForReport(),
        );
        $this->resetPage();

        if ($notify) {
            Notification::make()
                ->title('Report updated')
                ->body(count($this->report['rows']).' row(s)')
                ->success()
                ->send();
        }
    }

    protected function authorizeExport(): void
    {
        abort_unless($this->canExportReport(), 403);
    }

    /**
     * @return array<string, mixed>
     */
    protected function filtersForReport(): array
    {
        return [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'attendance_view' => $this->attendanceView,
            'course_id' => filled($this->courseId) ? (int) $this->courseId : null,
            'batch_id' => filled($this->batchId) ? (int) $this->batchId : null,
            'student_search' => trim($this->studentSearch),
            'max_percentage' => $this->attendanceView === 'student' && filled($this->maxPercentage)
                ? $this->maxPercentage
                : null,
        ];
    }
}

<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\ReportType;
use App\Models\ActivityAttendance;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Enquiry;
use App\Models\FeeStructure;
use App\Models\FeeInstallment;
use App\Models\HomeworkCheck;
use App\Models\Payment;
use App\Models\Student;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const MAX_DETAIL_ROWS = 5000;

    public function __construct(
        protected AttendanceService $attendance,
    ) {}

    /**
     * @param  array{
     *     date_from?: ?string,
     *     date_to?: ?string,
     *     course_id?: ?int,
     *     batch_id?: ?int,
     *     student_id?: ?int,
     *     lead_source?: ?string,
     *     user_id?: ?int,
     *     activity_type_id?: ?int,
     *     max_percentage?: ?float|int|string,
     * }  $filters
     * @return array{
     *     title: string,
     *     columns: array<int, string>,
     *     rows: array<int, array<int, string|int|float|null>>,
     *     generated_at: string
     * }
     */
    public function generate(ReportType $type, array $filters): array
    {
        [$from, $to] = $this->dateRange($filters);

        $report = match ($type) {
            ReportType::Enquiries => $this->enquiriesReport($from, $to),
            ReportType::LeadAging => $this->leadAgingReport(
                $filters['course_id'] ?? null,
                $filters['lead_source'] ?? null,
                $filters['user_id'] ?? null,
                isset($filters['min_days_open']) && $filters['min_days_open'] !== '' && $filters['min_days_open'] !== null
                    ? (int) $filters['min_days_open']
                    : null,
                isset($filters['min_days_since_contact']) && $filters['min_days_since_contact'] !== '' && $filters['min_days_since_contact'] !== null
                    ? (int) $filters['min_days_since_contact']
                    : null,
            ),
            ReportType::EnquirySources => $this->enquirySourcesReport($from, $to, $filters['lead_source'] ?? null),
            ReportType::AdmissionsByCourse => $this->admissionsByCourseReport($from, $to, $filters['course_id'] ?? null),
            ReportType::AdmissionsByStaff => $this->admissionsByStaffReport($from, $to, $filters['user_id'] ?? null),
            ReportType::ClassStudentAttendance => $this->classStudentAttendanceReport($from, $to, $filters),
            ReportType::AttendanceByBatch => $this->attendanceByBatchReport($from, $to, $filters['batch_id'] ?? null),
            ReportType::AttendanceByStudent => $this->attendanceByStudentReport(
                $from,
                $to,
                isset($filters['student_id']) ? (int) $filters['student_id'] : null,
            ),
            ReportType::DailyAbsentSheet => $this->dailyAbsentSheetReport($from, $to, $filters['batch_id'] ?? null),
            ReportType::MonthlyStudentAttendance => $this->monthlyStudentAttendanceReport($from, $to, $filters['batch_id'] ?? null, $filters['student_id'] ?? null),
            ReportType::LowAttendanceAlert => $this->lowAttendanceAlertReport(
                $from,
                $to,
                $filters['batch_id'] ?? null,
                isset($filters['max_percentage']) && $filters['max_percentage'] !== '' && $filters['max_percentage'] !== null
                    ? (float) $filters['max_percentage']
                    : 75.0,
            ),
            ReportType::Activities => $this->activitiesReport($from, $to, $filters['activity_type_id'] ?? null),
            ReportType::TestMarks => $this->testMarksReport(
                $from,
                $to,
                $filters['batch_id'] ?? null,
                $filters['activity_type_id'] ?? null,
            ),
            ReportType::FeeCollection => $this->feeCollectionReport($from, $to),
            ReportType::PendingFees => $this->pendingFeesReport($filters['course_id'] ?? null, $filters['batch_id'] ?? null),
            ReportType::OverdueInstallments => $this->overdueInstallmentsReport($filters['course_id'] ?? null, $filters['batch_id'] ?? null),
            ReportType::Discounts => $this->discountsReport($from, $to),
            ReportType::PaymentModes => $this->paymentModesReport($from, $to),
            ReportType::AuditLogs => $this->auditLogsReport($from, $to, $filters['user_id'] ?? null),
            ReportType::FinancialSummary => $this->financialSummaryReport($from, $to),
            ReportType::HomeworkCheckSummary => $this->homeworkCheckSummaryReport($from, $to, $filters['batch_id'] ?? null),
        };

        $report['generated_at'] = now()->format('d M Y H:i');

        return $report;
    }

    /**
     * @param  array{date_from?: ?string, date_to?: ?string}  $filters
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function dateRange(array $filters): array
    {
        $from = filled($filters['date_from'] ?? null)
            ? Carbon::parse($filters['date_from'])->startOfDay()
            : now()->startOfMonth();

        $to = filled($filters['date_to'] ?? null)
            ? Carbon::parse($filters['date_to'])->endOfDay()
            : now()->endOfDay();

        return [$from, $to];
    }

    /**
     * @param  array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}  $report
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function finalizeDetailReport(array $report): array
    {
        if (count($report['rows']) >= self::MAX_DETAIL_ROWS) {
            $report['title'] .= ' (first '.number_format(self::MAX_DETAIL_ROWS).' rows — narrow filters for more)';
        }

        return $report;
    }

    /**
     * @param  class-string<\BackedEnum&object{label(): string}>  $enumClass
     */
    protected function enumLabel(mixed $value, string $enumClass): string
    {
        if ($value instanceof $enumClass) {
            return $value->label();
        }

        if (is_string($value) || is_int($value)) {
            $enum = $enumClass::tryFrom($value);

            if ($enum !== null) {
                return $enum->label();
            }
        }

        return (string) $value;
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function enquiriesReport(Carbon $from, Carbon $to): array
    {
        $rows = Enquiry::query()
            ->with(['student', 'course'])
            ->whereBetween('created_at', [$from, $to])
            ->orderByDesc('created_at')
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(fn (Enquiry $enquiry): array => [
                $enquiry->created_at->format('d M Y'),
                $enquiry->enquiry_number,
                $enquiry->student?->name ?? '—',
                $enquiry->student?->mobile ?? '—',
                $enquiry->course?->name ?? '—',
                $enquiry->lead_source->label(),
            ])
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Enquiries · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Enquiry #', 'Student', 'Mobile', 'Course', 'Source'],
            'rows' => $rows,
        ]);
    }

    /**
     * Open pipeline leads with age since creation and since last call/visit.
     *
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function leadAgingReport(
        ?int $courseId,
        ?string $leadSource,
        ?int $userId,
        ?int $minDaysOpen,
        ?int $minDaysSinceContact,
    ): array {
        $query = Enquiry::query()
            ->activeLeads()
            ->with([
                'student' => fn ($studentQuery) => $studentQuery->withMax('visits as last_visit_date', 'visit_date'),
                'course',
                'meetingWith',
                'visits' => fn ($visitQuery) => $visitQuery
                    ->whereNotNull('next_follow_up_date')
                    ->orderByDesc('next_follow_up_date')
                    ->limit(1),
            ]);

        if ($courseId) {
            $query->where('course_id', $courseId);
        }

        if (filled($leadSource)) {
            $query->where('lead_source', $leadSource);
        }

        if ($userId) {
            $query->where('meeting_with_user_id', $userId);
        }

        $today = today();
        $rows = [];

        foreach ($query->limit(self::MAX_DETAIL_ROWS)->get() as $enquiry) {
            $student = $enquiry->student;
            $createdAt = $enquiry->created_at?->copy()->startOfDay() ?? $today->copy();
            $daysOpen = (int) $createdAt->diffInDays($today);

            $lastCallAt = $student?->last_call_at?->copy();
            $lastVisitDate = filled($student?->last_visit_date ?? null)
                ? Carbon::parse((string) $student->last_visit_date)->startOfDay()
                : null;

            $lastContactAt = collect([
                $lastCallAt?->copy()->startOfDay(),
                $lastVisitDate,
            ])->filter()->sort()->last();

            if ($lastContactAt instanceof Carbon) {
                $daysSinceContact = (int) $lastContactAt->diffInDays($today);
                $lastContactType = match (true) {
                    $lastCallAt && $lastVisitDate => $lastCallAt->startOfDay()->greaterThanOrEqualTo($lastVisitDate) ? 'Call' : 'Visit',
                    $lastCallAt !== null => 'Call',
                    $lastVisitDate !== null => 'Visit',
                    default => '—',
                };
            } else {
                $daysSinceContact = $daysOpen;
                $lastContactType = '—';
            }

            if ($minDaysOpen !== null && $daysOpen < $minDaysOpen) {
                continue;
            }

            if ($minDaysSinceContact !== null && $daysSinceContact < $minDaysSinceContact) {
                continue;
            }

            $nextVisitFollowUp = $enquiry->visits->first()?->next_follow_up_date;
            $nextFollowUpParts = [];

            if ($student?->next_call_followup_at) {
                $nextFollowUpParts[] = 'Call '.$student->next_call_followup_at->format('d M Y');
            }

            if ($nextVisitFollowUp) {
                $nextFollowUpParts[] = 'Visit '.$nextVisitFollowUp->format('d M Y');
            }

            $rows[] = [
                'days_since_contact' => $daysSinceContact,
                'days_open' => $daysOpen,
                'cells' => [
                    $enquiry->enquiry_number,
                    $student?->name ?? '—',
                    $student?->mobile ?? '—',
                    $enquiry->course?->name ?? '—',
                    $enquiry->lead_source->label(),
                    $enquiry->latest_visit_status?->label() ?? '—',
                    $enquiry->meetingWith?->name ?? '—',
                    $daysOpen,
                    $daysSinceContact,
                    $lastContactType,
                    $nextFollowUpParts !== [] ? implode(' · ', $nextFollowUpParts) : '—',
                ],
            ];
        }

        usort($rows, function (array $left, array $right): int {
            $byContact = $right['days_since_contact'] <=> $left['days_since_contact'];

            if ($byContact !== 0) {
                return $byContact;
            }

            return $right['days_open'] <=> $left['days_open'];
        });

        return $this->finalizeDetailReport([
            'title' => 'Open leads — aging · as of '.$today->format('d M Y'),
            'columns' => [
                'Enquiry #',
                'Student',
                'Mobile',
                'Course',
                'Source',
                'Status',
                'Assigned to',
                'Days open',
                'Days since contact',
                'Last contact',
                'Next follow-up',
            ],
            'rows' => array_map(
                static fn (array $row): array => $row['cells'],
                $rows,
            ),
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function enquirySourcesReport(Carbon $from, Carbon $to, ?string $source): array
    {
        $query = Enquiry::query()
            ->select('lead_source', DB::raw('COUNT(*) as total'))
            ->whereBetween('created_at', [$from, $to])
            ->groupBy('lead_source');

        if ($source) {
            $query->where('lead_source', $source);
        }

        $rows = $query->get()->map(function ($row): array {
            return [
                $this->enumLabel($row->lead_source, LeadSource::class),
                (int) $row->total,
            ];
        })->all();

        return [
            'title' => 'Enquiry sources · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Source', 'Count'],
            'rows' => $rows,
        ];
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function admissionsByCourseReport(Carbon $from, Carbon $to, ?int $courseId): array
    {
        $query = Admission::query()
            ->where('status', AdmissionStatus::Approved)
            ->whereBetween('approved_at', [$from, $to])
            ->with('enquiry.course');

        if ($courseId) {
            $query->whereHas('enquiry', fn ($q) => $q->where('course_id', $courseId));
        }

        $grouped = $query->get()
            ->groupBy(fn (Admission $a) => $a->enquiry?->course?->name ?? 'Unknown')
            ->map(fn (Collection $items, string $course): array => [
                $course,
                $items->count(),
                number_format((float) $items->sum('net_fee'), 2),
            ])
            ->values()
            ->all();

        return [
            'title' => 'Admissions by course · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Course', 'Admissions', 'Total net fee (₹)'],
            'rows' => $grouped,
        ];
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function admissionsByStaffReport(Carbon $from, Carbon $to, ?int $userId): array
    {
        $query = Admission::query()
            ->where('status', AdmissionStatus::Approved)
            ->whereBetween('approved_at', [$from, $to])
            ->with('approvedBy');

        if ($userId) {
            $query->where('approved_by_user_id', $userId);
        }

        $rows = $query->get()
            ->groupBy(fn (Admission $a) => $a->approvedBy?->name ?? 'Unknown')
            ->map(fn (Collection $items, string $staff): array => [
                $staff,
                $items->count(),
            ])
            ->values()
            ->all();

        return [
            'title' => 'Admissions by staff · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Staff', 'Approvals'],
            'rows' => $rows,
        ];
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function attendanceByBatchReport(Carbon $from, Carbon $to, ?int $batchId): array
    {
        $query = Attendance::query()
            ->with(['batch', 'student'])
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()]);

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        $rows = $query->orderBy('attendance_date')->limit(self::MAX_DETAIL_ROWS)->get()->map(fn (Attendance $row): array => [
            $row->attendance_date->format('d M Y'),
            $row->batch?->name ?? '—',
            $row->student?->name ?? '—',
            $row->status->code(),
        ])->all();

        return $this->finalizeDetailReport([
            'title' => 'Batch attendance · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Batch', 'Student', 'Status'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function attendanceByStudentReport(Carbon $from, Carbon $to, ?int $studentId): array
    {
        if (! $studentId) {
            return [
                'title' => 'Attendance by student — select a student',
                'columns' => ['Date', 'Batch', 'Status'],
                'rows' => [],
            ];
        }

        $student = Student::query()->find($studentId);

        $rows = Attendance::query()
            ->with('batch')
            ->where('student_id', $studentId)
            ->whereDate('attendance_date', '>=', $from->toDateString())
            ->whereDate('attendance_date', '<=', $to->toDateString())
            ->orderBy('attendance_date')
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(fn (Attendance $row): array => [
                $row->attendance_date->format('d M Y'),
                $row->batch?->name ?? '—',
                $row->status->label().' ('.$row->status->code().')',
            ])
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Attendance · '.($student?->name ?? 'Student').' · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Batch', 'Status'],
            'rows' => $rows,
        ]);
    }

    /**
     * Students enrolled in batch(es) with no Present/Leave on each day in range.
     *
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function dailyAbsentSheetReport(Carbon $from, Carbon $to, ?int $batchId): array
    {
        $days = $this->attendance->workingDatesBetween($from->copy()->startOfDay(), $to->copy()->startOfDay());
        $rows = [];

        $batches = Batch::query()
            ->when($batchId, fn ($q) => $q->whereKey($batchId))
            ->where('status', BatchStatus::Active)
            ->orderBy('name')
            ->get();

        foreach ($batches as $batch) {
            $students = \App\Models\BatchStudent::query()
                ->where('batch_id', $batch->id)
                ->where('is_active', true)
                ->with(['student.activeEnrollment'])
                ->get()
                ->pluck('student')
                ->filter()
                ->sortBy('name')
                ->values();

            if ($students->isEmpty()) {
                continue;
            }

            foreach ($days as $day) {
                if (count($rows) >= self::MAX_DETAIL_ROWS) {
                    break 2;
                }

                $creditedIds = Attendance::query()
                    ->where('batch_id', $batch->id)
                    ->whereDate('attendance_date', $day)
                    ->whereIn('status', [AttendanceStatus::Present, AttendanceStatus::Leave])
                    ->pluck('student_id')
                    ->all();

                $creditedLookup = array_fill_keys($creditedIds, true);

                foreach ($students as $student) {
                    if (isset($creditedLookup[$student->id])) {
                        continue;
                    }

                    $rows[] = [
                        Carbon::parse($day)->format('d M Y'),
                        $batch->name,
                        $student->name,
                        $student->activeEnrollment?->enrollment_number ?? '—',
                        $student->mobile ?? '—',
                        'Absent',
                    ];

                    if (count($rows) >= self::MAX_DETAIL_ROWS) {
                        break 3;
                    }
                }
            }
        }

        $scope = $batchId
            ? (Batch::query()->find($batchId)?->name ?? 'Batch')
            : 'All batches';

        return $this->finalizeDetailReport([
            'title' => 'Daily absent sheet · '.$scope.' · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Batch', 'Student', 'Roll', 'Mobile', 'Status'],
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function monthlyStudentAttendanceReport(Carbon $from, Carbon $to, ?int $batchId, ?int $studentId): array
    {
        $rows = $this->buildMonthlyAttendanceRows($from, $to, $batchId, $studentId, null);

        return $this->finalizeDetailReport([
            'title' => 'Monthly student attendance · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Student', 'Roll', 'Batch', 'Mobile', 'Present', 'Leave', 'Absent', 'Working days', '%'],
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function lowAttendanceAlertReport(Carbon $from, Carbon $to, ?int $batchId, float $maxPercentage): array
    {
        $rows = $this->buildMonthlyAttendanceRows($from, $to, $batchId, null, $maxPercentage);

        return $this->finalizeDetailReport([
            'title' => 'Low attendance (below '.$maxPercentage.'%) · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Student', 'Roll', 'Batch', 'Mobile', 'Present', 'Leave', 'Absent', 'Working days', '%'],
            'rows' => $rows,
        ]);
    }

    /**
     * Class summary, or one row per student sorted from the lowest percentage.
     * A student in two batches of the same class is counted once.
     *
     * @param  array<string, mixed>  $filters
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>, percent_column: int}
     */
    protected function classStudentAttendanceReport(Carbon $from, Carbon $to, array $filters): array
    {
        $view = ($filters['attendance_view'] ?? 'student') === 'class' ? 'class' : 'student';
        $rawMax = $filters['max_percentage'] ?? null;
        $maxPercentage = ($view === 'student' && $rawMax !== null && $rawMax !== '')
            ? (float) $rawMax
            : null;
        $lines = $this->classStudentAttendanceLines(
            $from,
            $to,
            filled($filters['course_id'] ?? null) ? (int) $filters['course_id'] : null,
            filled($filters['batch_id'] ?? null) ? (int) $filters['batch_id'] : null,
            filled($filters['student_id'] ?? null) ? (int) $filters['student_id'] : null,
            trim((string) ($filters['student_search'] ?? '')),
        );
        $period = $from->format('d M Y').' – '.$to->format('d M Y');

        if ($view === 'class') {
            return $this->finalizeDetailReport([
                'title' => 'Class attendance · '.$period,
                'columns' => ['Class', 'Students', 'Present', 'Leave', 'Absent', 'Working days', '%'],
                'rows' => $this->classAttendanceRows($lines),
                'percent_column' => 6,
            ]);
        }

        if ($maxPercentage !== null) {
            $lines = array_values(array_filter(
                $lines,
                fn (array $line): bool => $line['percentage'] < $maxPercentage,
            ));
        }

        usort($lines, function (array $a, array $b): int {
            $byPercent = $a['percentage'] <=> $b['percentage'];

            return $byPercent !== 0 ? $byPercent : strcmp($a['student_name'], $b['student_name']);
        });

        $rows = [];

        foreach ($lines as $line) {
            $rows[] = [
                $line['student_name'],
                $line['roll'],
                $line['class_name'],
                $line['batch_name'],
                $line['mobile'],
                $line['present'],
                $line['leave'],
                $line['absent'],
                $line['working'],
                $line['percentage'],
            ];

            if (count($rows) >= self::MAX_DETAIL_ROWS) {
                break;
            }
        }

        $title = $maxPercentage !== null
            ? 'Student attendance (below '.$maxPercentage.'%) · '.$period
            : 'Student attendance · '.$period;

        return $this->finalizeDetailReport([
            'title' => $title,
            'columns' => ['Student', 'Roll', 'Class', 'Batch', 'Mobile', 'Present', 'Leave', 'Absent', 'Working days', '%'],
            'rows' => $rows,
            'percent_column' => 9,
        ]);
    }

    /**
     * One line per student in a class. Two active batches in that class become one line.
     *
     * @return list<array{
     *     student_id: int,
     *     course_id: int,
     *     student_name: string,
     *     roll: string,
     *     class_name: string,
     *     batch_name: string,
     *     mobile: string,
     *     present: int,
     *     leave: int,
     *     absent: int,
     *     working: int,
     *     percentage: float
     * }>
     */
    protected function classStudentAttendanceLines(
        Carbon $from,
        Carbon $to,
        ?int $courseId,
        ?int $batchId,
        ?int $studentId,
        string $studentSearch = '',
    ): array {
        $assignments = BatchStudent::query()
            ->where('is_active', true)
            ->when($batchId, fn ($query) => $query->where('batch_id', $batchId))
            ->when($studentId, fn ($query) => $query->where('student_id', $studentId))
            ->when($studentSearch !== '', function ($query) use ($studentSearch): void {
                $query->whereHas('student', function ($student) use ($studentSearch): void {
                    $like = '%'.$studentSearch.'%';
                    $student->where(function ($inner) use ($like): void {
                        $inner->where('name', 'like', $like)
                            ->orWhere('mobile', 'like', $like);
                    });
                });
            })
            ->whereHas('batch', function ($query) use ($courseId): void {
                if ($courseId) {
                    $query->where('course_id', $courseId);
                }
            })
            ->with(['student.activeEnrollment', 'batch.course'])
            ->get()
            ->groupBy(fn (BatchStudent $row): string => $row->student_id.'-'.($row->batch?->course_id ?? 0));

        $lines = [];

        foreach ($assignments as $group) {
            /** @var BatchStudent|null $chosen */
            $chosen = $group->sortByDesc(
                fn (BatchStudent $row): int => $row->assigned_at?->getTimestamp() ?? 0
            )->first();
            $student = $chosen?->student;

            if (! $chosen || ! $student) {
                continue;
            }

            $joined = $chosen->assigned_at
                ? Carbon::parse($chosen->assigned_at)->startOfDay()
                : $from->copy()->startOfDay();
            $start = $joined->greaterThan($from) ? $joined->copy() : $from->copy()->startOfDay();
            $end = $to->copy()->startOfDay();

            if ($start->greaterThan($end)) {
                continue;
            }

            $dates = $this->calendarDates($start, $end);
            $marks = Attendance::query()
                ->where('batch_id', $chosen->batch_id)
                ->where('student_id', $student->id)
                ->whereBetween('attendance_date', [$start->toDateString(), $end->toDateString()])
                ->get(['attendance_date', 'status']);

            $presentDates = [];
            $leaveDates = [];

            foreach ($marks as $mark) {
                $date = $mark->attendance_date->toDateString();

                if (! in_array($date, $dates, true)) {
                    continue;
                }

                if ($mark->status === AttendanceStatus::Present) {
                    $presentDates[$date] = true;
                } elseif ($mark->status === AttendanceStatus::Leave) {
                    $leaveDates[$date] = true;
                }
            }

            $presentDays = count($presentDates);
            $leaveDays = count(array_diff_key($leaveDates, $presentDates));
            $totalDays = count($dates);

            $lines[] = [
                'student_id' => $student->id,
                'course_id' => (int) ($chosen->batch?->course_id ?? 0),
                'student_name' => $student->name,
                'roll' => $student->activeEnrollment?->enrollment_number ?? '—',
                'class_name' => $chosen->batch?->course?->name ?? '—',
                'batch_name' => $chosen->batch?->name ?? '—',
                'mobile' => $student->mobile ?? '—',
                'present' => $presentDays,
                'leave' => $leaveDays,
                'absent' => max(0, $totalDays - $presentDays - $leaveDays),
                'working' => $totalDays,
                'percentage' => $totalDays > 0
                    ? round(($presentDays / $totalDays) * 100, 1)
                    : 0,
            ];
        }

        return $lines;
    }

    /**
     * Every date from the start through the end, including Sunday.
     *
     * @return list<string>
     */
    protected function calendarDates(Carbon $from, Carbon $to): array
    {
        $dates = [];
        $cursor = $from->copy()->startOfDay();
        $end = $to->copy()->startOfDay();

        while ($cursor->lte($end)) {
            $dates[] = $cursor->toDateString();
            $cursor->addDay();
        }

        return $dates;
    }

    /**
     * @param  list<array<string, mixed>>  $lines
     * @return list<array<int, string|int|float|null>>
     */
    protected function classAttendanceRows(array $lines): array
    {
        $classes = [];

        foreach ($lines as $line) {
            $key = (int) $line['course_id'];

            if (! isset($classes[$key])) {
                $classes[$key] = [
                    'name' => (string) $line['class_name'],
                    'students' => 0,
                    'present' => 0,
                    'leave' => 0,
                    'absent' => 0,
                    'working' => 0,
                    'percentage_total' => 0.0,
                ];
            }

            $classes[$key]['students']++;
            $classes[$key]['present'] += $line['present'];
            $classes[$key]['leave'] += $line['leave'];
            $classes[$key]['absent'] += $line['absent'];
            $classes[$key]['working'] += $line['working'];
            $classes[$key]['percentage_total'] += $line['percentage'];
        }

        $rows = [];

        foreach ($classes as $class) {
            $rows[] = [
                $class['name'],
                $class['students'],
                $class['present'],
                $class['leave'],
                $class['absent'],
                $class['working'],
                $class['students'] > 0
                    ? round($class['percentage_total'] / $class['students'], 1)
                    : 0,
            ];
        }

        usort($rows, function (array $a, array $b): int {
            $byPercent = ((float) $a[6]) <=> ((float) $b[6]);

            return $byPercent !== 0 ? $byPercent : strcmp((string) $a[0], (string) $b[0]);
        });

        return array_slice($rows, 0, self::MAX_DETAIL_ROWS);
    }

    /**
     * @return array<int, array<int, string|int|float|null>>
     */
    protected function buildMonthlyAttendanceRows(
        Carbon $from,
        Carbon $to,
        ?int $batchId,
        ?int $studentId,
        ?float $maxPercentage,
    ): array {
        $batchStudents = \App\Models\BatchStudent::query()
            ->where('is_active', true)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->when($studentId, fn ($q) => $q->where('student_id', $studentId))
            ->with(['student.activeEnrollment', 'batch'])
            ->get();

        $rows = [];

        foreach ($batchStudents as $batchStudent) {
            $student = $batchStudent->student;

            if (! $student) {
                continue;
            }

            // Temporarily use this batch assignment for summary math.
            $student->setRelation('activeBatchStudent', $batchStudent);

            $summary = $this->attendance->summaryForStudentInRange(
                $student,
                $from->copy()->startOfDay(),
                $to->copy()->startOfDay(),
            );

            if ($summary === null) {
                continue;
            }

            if ($maxPercentage !== null && $summary['percentage'] >= $maxPercentage) {
                continue;
            }

            $rows[] = [
                $student->name,
                $student->activeEnrollment?->enrollment_number ?? '—',
                $batchStudent->batch?->name ?? '—',
                $student->mobile ?? '—',
                $summary['present_days'],
                $summary['leave_days'],
                $summary['absent_days'],
                $summary['expected_days'],
                $summary['percentage'],
            ];

            if (count($rows) >= self::MAX_DETAIL_ROWS) {
                break;
            }
        }

        usort($rows, fn (array $a, array $b): int => ((float) $a[8]) <=> ((float) $b[8]));

        return $rows;
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function activitiesReport(Carbon $from, Carbon $to, int|string|null $activityTypeId): array
    {
        $query = ActivityAttendance::query()
            ->where('is_present', true)
            ->where('attendable_type', ActivitySession::class)
            ->with(['student', 'attendable.activityType', 'attendable.batch']);

        if ($activityTypeId) {
            $query->whereHasMorph('attendable', [ActivitySession::class], function ($builder) use ($activityTypeId): void {
                $builder->where('activity_type_id', (int) $activityTypeId);
            });
        }

        $rows = $query
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->filter(function (ActivityAttendance $row) use ($from, $to): bool {
                $activity = $row->attendable;

                if (! $activity instanceof ActivitySession) {
                    return false;
                }

                return $activity->session_date->between($from, $to);
            })
            ->map(fn (ActivityAttendance $row): array => [
                $row->attendable?->activityType?->name ?? '—',
                $row->attendable?->displayTitle() ?? '—',
                $row->student?->name ?? '—',
                $row->student?->mobile ?? '—',
                $row->marks_obtained !== null
                    ? rtrim(rtrim(number_format((float) $row->marks_obtained, 2), '0'), '.')
                    : ($row->grade ?? '—'),
            ])
            ->values()
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Activity participation · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Type', 'Activity', 'Student', 'Mobile', 'Marks / Grade'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function testMarksReport(
        Carbon $from,
        Carbon $to,
        ?int $batchId,
        int|string|null $activityTypeId,
    ): array {
        $query = ActivityAttendance::query()
            ->where('is_present', true)
            ->whereNotNull('marks_obtained')
            ->where('attendable_type', ActivitySession::class)
            ->with([
                'student.activeEnrollment',
                'attendable.batch',
                'attendable.activityType',
            ])
            ->whereHasMorph('attendable', [ActivitySession::class], function ($builder) use ($from, $to, $batchId, $activityTypeId): void {
                $builder->whereBetween('session_date', [$from->toDateString(), $to->toDateString()]);

                if ($batchId) {
                    $builder->where('batch_id', $batchId);
                }

                if ($activityTypeId) {
                    $builder->where('activity_type_id', (int) $activityTypeId);
                }
            })
            ->orderByDesc('updated_at')
            ->limit(self::MAX_DETAIL_ROWS);

        $rows = $query->get()->map(function (ActivityAttendance $row): array {
            $session = $row->attendable;
            $maxMarks = $session instanceof ActivitySession && filled($session->metadataValue('max_marks'))
                ? (float) $session->metadataValue('max_marks')
                : null;
            $marks = $row->marks_obtained !== null ? (float) $row->marks_obtained : null;

            return [
                $session?->session_date?->format('d M Y') ?? '—',
                $session?->activityType?->name ?? '—',
                $session instanceof ActivitySession ? StudentExamMarksMatrix::testLabelForSession($session) : '—',
                $session instanceof ActivitySession ? StudentExamMarksMatrix::subjectForSession($session) : '—',
                $session?->batch?->name ?? '—',
                $row->student?->activeEnrollment?->enrollment_number ?? '—',
                $row->student?->name ?? '—',
                StudentExamMarksMatrix::formatMarks($marks, $maxMarks, $row->grade),
            ];
        })->all();

        return $this->finalizeDetailReport([
            'title' => 'Test marks · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Exam type', 'Test', 'Subject', 'Batch', 'Roll #', 'Student', 'Marks'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function feeCollectionReport(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->active()
            ->with(['student', 'addedBy'])
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->orderByDesc('payment_date')
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(fn (Payment $payment): array => [
                $payment->payment_date->format('d M Y'),
                $payment->receipt_number,
                $payment->student?->name ?? '—',
                $payment->payment_mode->label(),
                number_format((float) $payment->amount, 2),
                $payment->addedBy?->name ?? '—',
            ])
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Fee collection · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Receipt #', 'Student', 'Mode', 'Amount (₹)', 'Collected by'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function pendingFeesReport(?int $courseId, ?int $batchId): array
    {
        $query = FeeStructure::query()
            ->forActiveEnrollments()
            ->with('enrollment.student', 'enrollment.course')
            ->where('pending_amount', '>', 0);

        if ($courseId) {
            $query->whereHas('enrollment', fn ($q) => $q->where('course_id', $courseId));
        }

        if ($batchId) {
            $query->whereHas('enrollment.student.activeBatchStudent', fn ($q) => $q->where('batch_id', $batchId));
        }

        $rows = $query
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(fn (FeeStructure $fee): array => [
            $fee->enrollment?->student?->name ?? '—',
            $fee->enrollment?->student?->mobile ?? '—',
            $fee->enrollment?->course?->name ?? '—',
            number_format((float) $fee->net_fee, 2),
            number_format((float) $fee->paid_amount, 2),
            number_format((float) $fee->pending_amount, 2),
        ])->all();

        return $this->finalizeDetailReport([
            'title' => 'Pending fees',
            'columns' => ['Student', 'Mobile', 'Course', 'Net (₹)', 'Paid (₹)', 'Pending (₹)'],
            'rows' => $rows,
        ]);
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function overdueInstallmentsReport(?int $courseId, ?int $batchId): array
    {
        $query = FeeInstallment::query()
            ->with('feeStructure.enrollment.student', 'feeStructure.enrollment.course')
            ->whereHas('feeStructure', fn ($q) => $q->forActiveEnrollments())
            ->where('pending_amount', '>', 0)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '<', now()->toDateString());

        if ($courseId) {
            $query->whereHas('feeStructure.enrollment', fn ($q) => $q->where('course_id', $courseId));
        }

        if ($batchId) {
            $query->whereHas(
                'feeStructure.enrollment.student.activeBatchStudent',
                fn ($q) => $q->where('batch_id', $batchId),
            );
        }

        $rows = $query
            ->orderBy('due_date')
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(function (FeeInstallment $installment): array {
                $student = $installment->feeStructure?->enrollment?->student;
                $course = $installment->feeStructure?->enrollment?->course;
                $daysOverdue = $installment->due_date?->diffInDays(now()) ?? 0;

                return [
                    $student?->name ?? '—',
                    $student?->mobile ?? '—',
                    $course?->name ?? '—',
                    $installment->label,
                    $installment->due_date?->format('d M Y') ?? '—',
                    number_format((float) $installment->pending_amount, 2),
                    (int) $daysOverdue,
                ];
            })
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Overdue installments',
            'columns' => ['Student', 'Mobile', 'Course', 'Installment', 'Due date', 'Pending (₹)', 'Days overdue'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function discountsReport(Carbon $from, Carbon $to): array
    {
        $rows = Admission::query()
            ->where('status', AdmissionStatus::Approved)
            ->where('discount_amount', '>', 0)
            ->whereBetween('approved_at', [$from, $to])
            ->with(['student', 'enquiry.course'])
            ->orderByDesc('approved_at')
            ->limit(self::MAX_DETAIL_ROWS)
            ->get()
            ->map(fn (Admission $admission): array => [
                $admission->approved_at?->format('d M Y') ?? '—',
                $admission->admission_number,
                $admission->student?->name ?? '—',
                $admission->enquiry?->course?->name ?? '—',
                number_format((float) $admission->course_fee, 2),
                number_format((float) $admission->discount_amount, 2),
                number_format((float) $admission->net_fee, 2),
            ])
            ->all();

        return $this->finalizeDetailReport([
            'title' => 'Discounts · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Admission #', 'Student', 'Course', 'Course fee (₹)', 'Discount (₹)', 'Net (₹)'],
            'rows' => $rows,
        ]);
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function paymentModesReport(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()
            ->active()
            ->select('payment_mode', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('payment_mode')
            ->get()
            ->map(function ($row): array {
                return [
                    $this->enumLabel($row->payment_mode, PaymentMode::class),
                    (int) $row->count,
                    number_format((float) $row->total, 2),
                ];
            })
            ->all();

        return [
            'title' => 'Payment modes · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Mode', 'Transactions', 'Total (₹)'],
            'rows' => $rows,
        ];
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function auditLogsReport(Carbon $from, Carbon $to, ?int $userId): array
    {
        $query = AuditLog::query()
            ->whereBetween('created_at', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $rows = $query->orderByDesc('created_at')->limit(500)->get()->map(fn (AuditLog $log): array => [
            $log->created_at->format('d M Y H:i'),
            $log->user_name,
            $log->user_role,
            $log->action,
            $log->reason ?? '—',
        ])->all();

        return [
            'title' => 'Audit logs · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['When', 'User', 'Role', 'Action', 'Reason'],
            'rows' => $rows,
        ];
    }

  /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function financialSummaryReport(Carbon $from, Carbon $to): array
    {
        $collected = (float) Payment::query()
            ->active()
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->sum('amount');

        $pending = (float) FeeStructure::query()->forActiveEnrollments()->sum('pending_amount');
        $discounts = (float) Admission::query()
            ->where('status', AdmissionStatus::Approved)
            ->whereBetween('approved_at', [$from, $to])
            ->sum('discount_amount');

        $rows = [
            ['Fee collected (period)', number_format($collected, 2)],
            ['Total pending (all students)', number_format($pending, 2)],
            ['Discounts given (period)', number_format($discounts, 2)],
            ['Payments count (period)', (string) Payment::query()->active()->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])->count()],
        ];

        return [
            'title' => 'Financial summary · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Metric', 'Value (₹)'],
            'rows' => $rows,
        ];
    }

    /**
     * @return array{title: string, columns: array<int, string>, rows: array<int, array<int, string|int|float|null>>}
     */
    protected function homeworkCheckSummaryReport(Carbon $from, Carbon $to, ?int $batchId): array
    {
        $checks = HomeworkCheck::query()
            ->with(['batch'])
            ->whereBetween('checked_on', [$from->toDateString(), $to->toDateString()])
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->orderBy('checked_on')
            ->orderBy('batch_id')
            ->get();

        $activeCounts = BatchStudent::query()
            ->where('is_active', true)
            ->when($batchId, fn ($q) => $q->where('batch_id', $batchId))
            ->selectRaw('batch_id, COUNT(*) as total')
            ->groupBy('batch_id')
            ->pluck('total', 'batch_id');

        $rows = [];

        $sessions = $checks->groupBy(function (HomeworkCheck $check): string {
            return implode('|', [
                (string) $check->batch_id,
                (string) $check->course_subject_id,
                $check->checked_on?->toDateString() ?? '',
            ]);
        });

        foreach ($sessions as $group) {
            /** @var HomeworkCheck $first */
            $first = $group->first();
            $latest = $group->sortByDesc('id')->unique('student_id');
            $done = $latest->where('status', HomeworkCheckStatus::Done)->count();
            $notDone = $latest->where('status', HomeworkCheckStatus::NotDone)->count();
            $active = (int) ($activeCounts[$first->batch_id] ?? 0);
            $unmarked = max(0, $active - $latest->count());
            $donePct = $active > 0 ? (int) round(($done / $active) * 100) : 0;

            $rows[] = [
                $first->checked_on?->format('d M Y') ?? '—',
                $first->batch?->name ?? '—',
                $first->subject_name,
                $active,
                $done,
                $notDone,
                $unmarked,
                $donePct.'%',
            ];
        }

        usort($rows, fn (array $a, array $b): int => strcmp((string) $a[0], (string) $b[0]) ?: strcmp((string) $a[1], (string) $b[1]));

        return $this->finalizeDetailReport([
            'title' => 'Homework check Done % · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Date', 'Class', 'Subject', 'Students', 'Done', 'Not Done', 'Unmarked', 'Done %'],
            'rows' => $rows,
        ]);
    }
}

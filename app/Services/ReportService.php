<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\LeadSource;
use App\Enums\PaymentMode;
use App\Enums\ReportType;
use App\Models\ActivityAttendance;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Batch;
use App\Models\Enquiry;
use App\Models\FeeStructure;
use App\Models\FeeInstallment;
use App\Models\Payment;
use App\Models\Student;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ReportService
{
    public const MAX_DETAIL_ROWS = 5000;

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
            ReportType::EnquirySources => $this->enquirySourcesReport($from, $to, $filters['lead_source'] ?? null),
            ReportType::AdmissionsByCourse => $this->admissionsByCourseReport($from, $to, $filters['course_id'] ?? null),
            ReportType::AdmissionsByStaff => $this->admissionsByStaffReport($from, $to, $filters['user_id'] ?? null),
            ReportType::AttendanceByBatch => $this->attendanceByBatchReport($from, $to, $filters['batch_id'] ?? null),
            ReportType::AttendanceByStudent => $this->attendanceByStudentReport($from, $to, $filters['student_id'] ?? null),
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
            $enum = LeadSource::tryFrom($row->lead_source);

            return [
                $enum?->label() ?? $row->lead_source,
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
                'title' => 'Attendance by student',
                'columns' => ['Date', 'Batch', 'Status'],
                'rows' => [],
            ];
        }

        $student = Student::query()->find($studentId);

        $rows = Attendance::query()
            ->with('batch')
            ->where('student_id', $studentId)
            ->whereBetween('attendance_date', [$from->toDateString(), $to->toDateString()])
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
            ->select('payment_mode', DB::raw('COUNT(*) as count'), DB::raw('SUM(amount) as total'))
            ->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])
            ->groupBy('payment_mode')
            ->get()
            ->map(function ($row): array {
                $mode = PaymentMode::tryFrom($row->payment_mode);

                return [
                    $mode?->label() ?? $row->payment_mode,
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
            ['Payments count (period)', (string) Payment::query()->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])->count()],
        ];

        return [
            'title' => 'Financial summary · '.$from->format('d M Y').' – '.$to->format('d M Y'),
            'columns' => ['Metric', 'Value (₹)'],
            'rows' => $rows,
        ];
    }
}

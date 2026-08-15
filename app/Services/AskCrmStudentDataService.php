<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Models\ActivityType;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Document;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmAccess;
use App\Support\FeatureGate;

class AskCrmStudentDataService
{
    public function __construct(
        protected AttendanceService $attendance,
        protected HomeworkCheckService $homeworkChecks,
        protected HomeworkAssignmentService $homeworkAssignments,
        protected StudentCounterService $counters,
        protected LeadTimelineService $leadTimeline,
        protected StudentCaseService $cases,
        protected StudentWhatsAppThreadService $whatsappThread,
        protected ActivityAttendanceService $activityAttendance,
        protected FeeDiscountHistoryService $feeDiscountHistory,
    ) {}

    /**
     * Read-only CRM snapshot — mirrors student profile tabs (Overview → Exams).
     *
     * @return array<string, mixed>
     */
    public function snapshot(User $user, Student $student, ?string $referencedDate = null): array
    {
        $student->loadMissing([
            'activeEnrollment.feeStructure.installments',
            'activeEnrollment.feeStructure.penalties',
            'activeEnrollment.feeStructure.miscCharges',
            'activeEnrollment.course',
            'activeEnrollment.academicSession',
            'activeEnrollment.admission',
            'activeBatchStudent.batch',
            'latestEnquiry',
            'admissions',
        ]);

        $batch = $student->activeBatchStudent?->batch;
        $studentId = (int) $student->id;

        return [
            'meta' => [
                'today' => now()->toDateString(),
                'referenced_date' => $referencedDate,
                'source' => 'student_profile_tabs',
            ],
            'student' => $this->studentIdentity($student, $batch?->name),
            'custom_fields' => $this->customFieldsSnapshot($student),
            'profile_summary' => $this->profileSummary($student),
            'overview' => $this->overviewSnapshot($student),
            'visits' => $this->visitsSnapshot($student),
            'calls' => $this->callsSnapshot($student),
            'cases' => $this->casesSnapshot($user, $student),
            'messages' => $this->messagesSnapshot($student),
            'documents' => $this->documentsSnapshot($student),
            'fees' => $this->feesSnapshot($user, $student),
            'receipts' => $this->receiptsSnapshot($user, $student),
            'attendance' => $this->attendanceSnapshot($student, $batch?->id, $referencedDate),
            'homework' => $this->homeworkSnapshot($studentId, $referencedDate, $student),
            'exams' => $this->examsSnapshot($student),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function studentIdentity(Student $student, ?string $batchName): array
    {
        return [
            'id' => (int) $student->id,
            'name' => $student->name,
            'father_name' => $student->father_name,
            'status' => $student->status->label(),
            'gender' => $student->gender?->label(),
            'category' => $student->category?->label(),
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'class' => $batchName,
            'course' => $student->activeEnrollment?->course?->name,
            'session' => $student->activeEnrollment?->academicSession?->name,
            'roll' => $student->activeEnrollment?->enrollment_number,
            'admission_number' => $student->activeEnrollment?->admission?->admission_number
                ?? $student->admissions()->latest()->value('admission_number'),
            'mobile' => $student->mobile,
            'alternate_mobile' => $student->alternate_mobile,
            'email' => $student->email,
            'address' => trim(implode(', ', array_filter([
                $student->address,
                $student->city,
                $student->state,
                $student->pincode,
            ]))),
            'call_pipeline' => [
                'total_calls' => (int) $student->total_calls,
                'last_call_at' => $student->last_call_at?->toDateString(),
                'last_call_status' => $student->last_call_status?->label(),
                'next_followup_at' => $student->next_call_followup_at?->toDateString(),
                'is_call_blocked' => (bool) $student->is_call_blocked,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function profileSummary(Student $student): array
    {
        $profile = $this->counters->profile($student);

        return [
            'phase' => $profile['phase']->value,
            'counters' => $profile['items'],
            'lead_source' => [
                'headline' => $profile['lead_sources']['headline'] ?? null,
                'detail' => $profile['lead_sources']['detail'] ?? null,
            ],
        ];
    }

    /**
     * Overview tab — academic record + enrollment (same as profile Overview).
     *
     * @return array<string, mixed>
     */
    protected function overviewSnapshot(Student $student): array
    {
        $enrollment = $student->activeEnrollment;
        $admission = $enrollment?->admission
            ?? $student->admissions()->latest()->first();

        return [
            'enrollment' => $enrollment ? [
                'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
                'course' => $enrollment->course?->name,
                'session' => $enrollment->academicSession?->name,
                'roll' => $enrollment->enrollment_number,
                'status' => $enrollment->status->label(),
            ] : null,
            'academic_record' => $admission ? [
                'admission_number' => $admission->admission_number,
                'status' => $admission->status->label(),
                'class_10_board' => $admission->tenth_board,
                'class_10_percentage' => $admission->tenth_percentage,
                'class_12_board' => $admission->twelfth_board,
                'class_12_percentage' => $admission->twelfth_percentage,
                'graduation' => $admission->graduation,
                'graduation_percentage' => $admission->graduation_percentage,
            ] : null,
            'latest_enquiry' => $student->latestEnquiry ? [
                'enquiry_number' => $student->latestEnquiry->enquiry_number,
                'lead_source' => $student->latestEnquiry->lead_source?->label(),
                'meeting_for' => $student->latestEnquiry->meeting_for,
                'visit_status' => $student->latestEnquiry->latest_visit_status,
            ] : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function visitsSnapshot(Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            return ['enabled' => false];
        }

        $limit = $this->listLimit();
        $sequenceMap = $this->leadTimeline->visitSequenceMap($student);

        $visits = $student->visits()
            ->inPerson()
            ->with(['staff', 'enquiry.course'])
            ->orderByDesc('visit_date')
            ->limit($limit)
            ->get()
            ->map(function (Visit $visit) use ($sequenceMap): array {
                $sequence = $sequenceMap[$visit->id] ?? 1;

                return [
                    'date' => $visit->visit_date?->toDateString(),
                    'type' => $visit->isCampusVisit() ? 'campus_visit' : 'visit',
                    'label' => $visit->isCampusVisit() ? 'Campus visit' : 'Visit #'.$sequence,
                    'summary' => $visit->discussion_summary,
                    'remarks' => $visit->remarks,
                    'staff' => $visit->staff?->name,
                    'status' => $visit->displayStatusLabel(),
                    'next_follow_up' => $visit->next_follow_up_date?->toDateString(),
                    'course' => $visit->enquiry?->course?->name,
                ];
            })
            ->values()
            ->all();

        return [
            'enabled' => true,
            'count' => count($visits),
            'recent' => $visits,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function callsSnapshot(Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls)) {
            return ['enabled' => false];
        }

        $calls = $student->calls()
            ->with(['staff', 'enquiry.course', 'studentCase'])
            ->orderByDesc('called_at')
            ->orderByDesc('id')
            ->limit($this->listLimit())
            ->get()
            ->map(fn (StudentCall $call): array => [
                'called_at' => $call->called_at?->toDateString(),
                'called_at_time' => $call->called_at?->format('h:i A'),
                'direction' => $call->call_direction->label(),
                'status' => $call->call_status->label(),
                'staff' => $call->staff?->name,
                'notes' => $call->call_notes,
                'duration_minutes' => $call->duration_minutes,
                'duration_seconds' => $call->duration_seconds,
                'duration_label' => $call->durationLabel(),
                'next_followup_at' => $call->next_followup_at?->toDateString(),
                'case_number' => $call->studentCase?->case_number,
                'course' => $call->enquiry?->course?->name,
            ])
            ->values()
            ->all();

        return [
            'enabled' => true,
            'count' => count($calls),
            'recent' => $calls,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function casesSnapshot(User $user, Student $student): array
    {
        if ($student->activeEnrollment === null) {
            return ['enabled' => false, 'reason' => 'not_enrolled'];
        }

        if (! CrmAccess::can($user, CrmPermission::CasesView)) {
            return ['enabled' => true, 'can_view' => false];
        }

        $cases = $this->cases->forStudent($student, $user)
            ->take($this->listLimit())
            ->map(fn ($case): array => [
                'case_number' => $case->case_number,
                'title' => $case->title,
                'type' => $case->case_type->label(),
                'status' => $case->status->label(),
                'assignee' => $case->currentAssignee?->name,
                'opened_at' => $case->opened_at?->toDateString(),
                'closed_at' => $case->closed_at?->toDateString(),
                'summary' => $case->summary,
            ])
            ->values()
            ->all();

        $openCases = $this->cases->overviewBanners($student, $user);

        return [
            'enabled' => true,
            'can_view' => true,
            'open_count' => count($openCases),
            'open_cases' => $openCases,
            'recent' => $cases,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function messagesSnapshot(Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return ['enabled' => false];
        }

        try {
            $thread = $this->whatsappThread->threadForStudent($student)
                ->take($this->listLimit())
                ->map(fn ($item): array => $item->toArray())
                ->values()
                ->all();

            return [
                'enabled' => true,
                'session_open' => $this->whatsappThread->sessionOpenForStudent($student),
                'count' => count($thread),
                'recent' => $thread,
            ];
        } catch (\Throwable) {
            return [
                'enabled' => true,
                'error' => 'Could not load WhatsApp thread',
                'recent' => [],
            ];
        }
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentsSnapshot(Student $student): array
    {
        $admissionIds = $student->admissions()->pluck('id');

        $documents = Document::query()
            ->where('documentable_type', Admission::class)
            ->whereIn('documentable_id', $admissionIds)
            ->latest()
            ->limit($this->listLimit())
            ->get()
            ->map(fn (Document $document): array => [
                'type' => $document->type->label(),
                'filename' => $document->original_filename,
                'uploaded_at' => $document->created_at?->toDateString(),
            ])
            ->values()
            ->all();

        return [
            'enabled' => true,
            'count' => count($documents),
            'items' => $documents,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function feesSnapshot(User $user, Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return ['enabled' => false];
        }

        if ($student->activeEnrollment === null) {
            return ['enabled' => false, 'reason' => 'not_enrolled'];
        }

        if (! CrmAccess::canViewFees($user)) {
            return [
                'enabled' => true,
                'can_view' => false,
            ];
        }

        $fees = $student->activeEnrollment?->feeStructure;

        if (! $fees) {
            return [
                'enabled' => true,
                'can_view' => true,
                'has_fee_structure' => false,
            ];
        }

        $tuitionPending = (float) $fees->pending_amount;
        $totalPending = (float) $fees->totalCollectiblePending();
        $discountSummary = $this->feeDiscountHistory->studentSummary($student);

        $installments = $fees->installments
            ->sortBy(fn ($row): array => [
                $row->due_date?->timestamp ?? PHP_INT_MAX,
                $row->sort_order,
                $row->id,
            ])
            ->take($this->listLimit())
            ->map(fn ($row): array => [
                'label' => $row->label,
                'amount' => (float) $row->amount,
                'paid_amount' => (float) $row->paid_amount,
                'pending_amount' => (float) $row->pending_amount,
                'due_date' => $row->due_date?->toDateString(),
                'status' => $row->statusLabel(),
            ])
            ->values()
            ->all();

        $penalties = $fees->penalties
            ->take($this->listLimit())
            ->map(fn ($row): array => [
                'description' => $row->description,
                'type' => $row->penalty_type->label(),
                'amount' => (float) $row->penalty_amount,
                'penalty_date' => $row->penalty_date?->toDateString(),
                'status' => $row->status->label(),
            ])
            ->values()
            ->all();

        return [
            'enabled' => true,
            'can_view' => true,
            'has_fee_structure' => true,
            'course_fee' => (float) $fees->course_fee,
            'discount_amount' => (float) $fees->discount_amount,
            'net_fee' => (float) $fees->net_fee,
            'tuition_pending' => $tuitionPending,
            'tuition_pending_formatted' => number_format($tuitionPending, 2),
            'paid_amount' => (float) $fees->paid_amount,
            'paid_amount_formatted' => number_format((float) $fees->paid_amount, 2),
            'total_pending' => $totalPending,
            'total_pending_formatted' => number_format($totalPending, 2),
            'is_clear' => $tuitionPending <= 0.009,
            'discount_summary' => $discountSummary,
            'installment_count' => count($installments),
            'installments' => $installments,
            'penalties' => $penalties,
            'misc_charges' => $fees->miscCharges
                ->take($this->listLimit())
                ->map(fn ($row): array => [
                    'label' => $row->label ?? $row->description ?? 'Charge',
                    'kind' => $row->kind?->label() ?? (string) $row->kind,
                    'amount' => (float) ($row->amount ?? 0),
                    'pending_amount' => (float) $row->pendingAmount(),
                    'status' => $row->status?->label() ?? (string) $row->status,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function customFieldsSnapshot(Student $student): array
    {
        $definitions = app(CustomFieldService::class)->activeDefinitions(CustomFieldService::ENTITY_STUDENT);
        $data = is_array($student->custom_data) ? $student->custom_data : [];

        if ($definitions === [] && $data === []) {
            return ['enabled' => true, 'fields' => []];
        }

        $fields = [];

        foreach ($definitions as $definition) {
            $key = $definition->field_key;
            $value = $data[$key] ?? null;

            $fields[] = [
                'key' => $key,
                'label' => $definition->label,
                'value' => $value,
            ];
        }

        foreach ($data as $key => $value) {
            if (collect($fields)->contains(fn (array $row): bool => $row['key'] === $key)) {
                continue;
            }

            $fields[] = [
                'key' => $key,
                'label' => $key,
                'value' => $value,
            ];
        }

        return [
            'enabled' => true,
            'fields' => $fields,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function receiptsSnapshot(User $user, Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees)) {
            return ['enabled' => false];
        }

        if (! CrmAccess::canViewFees($user)) {
            return ['enabled' => true, 'can_view' => false];
        }

        $payments = Payment::query()
            ->where('student_id', $student->id)
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($this->listLimit())
            ->get()
            ->map(fn (Payment $payment): array => [
                'receipt_number' => $payment->receipt_number,
                'payment_date' => $payment->payment_date?->toDateString(),
                'amount' => (float) $payment->amount,
                'amount_formatted' => number_format((float) $payment->amount, 2),
                'mode' => $payment->payment_mode->label(),
                'installment' => $payment->feeInstallment?->label,
                'has_receipt_pdf' => filled($payment->receipt_path),
            ])
            ->values()
            ->all();

        return [
            'enabled' => true,
            'can_view' => true,
            'count' => count($payments),
            'recent' => $payments,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function attendanceSnapshot(Student $student, ?int $batchId, ?string $referencedDate): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return ['enabled' => false];
        }

        if ($student->activeEnrollment === null) {
            return ['enabled' => false, 'reason' => 'not_enrolled'];
        }

        $today = now()->toDateString();
        $todayRow = $batchId ? $this->attendanceRowForDate($student->id, $batchId, $today) : null;
        $month = $this->attendance->monthToDateSummaryForStudent($student);

        $recentDays = [];

        if ($batchId) {
            $recentDays = Attendance::query()
                ->where('student_id', $student->id)
                ->where('batch_id', $batchId)
                ->whereDate('attendance_date', '>=', now()->subDays(45)->toDateString())
                ->orderByDesc('attendance_date')
                ->limit(45)
                ->get()
                ->map(fn (Attendance $row): array => [
                    'date' => $row->attendance_date->toDateString(),
                    'status' => $row->status->label(),
                    'checked_in_at' => $row->checked_in_at?->format('h:i A'),
                    'checked_out_at' => $row->checked_out_at?->format('h:i A'),
                ])
                ->values()
                ->all();
        }

        $onReferencedDate = null;

        if (filled($referencedDate) && $batchId) {
            $onReferencedDate = [
                'date' => $referencedDate,
                'record' => $this->attendanceRowForDate($student->id, $batchId, $referencedDate),
            ];
        }

        return [
            'enabled' => true,
            'has_active_class' => $batchId !== null,
            'today' => $todayRow ?? [
                'date' => $today,
                'status' => 'not_marked_yet',
            ],
            'month' => $month,
            'recent_days' => $recentDays,
            'on_referenced_date' => $onReferencedDate,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function attendanceRowForDate(int $studentId, int $batchId, string $date): ?array
    {
        $row = Attendance::query()
            ->where('student_id', $studentId)
            ->where('batch_id', $batchId)
            ->whereDate('attendance_date', $date)
            ->first();

        if (! $row) {
            return [
                'date' => $date,
                'status' => 'not_marked',
            ];
        }

        return [
            'date' => $date,
            'status' => $row->status->label(),
            'checked_in_at' => $row->checked_in_at?->format('h:i A'),
            'checked_out_at' => $row->checked_out_at?->format('h:i A'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function homeworkSnapshot(int $studentId, ?string $referencedDate = null, ?Student $student = null): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return ['enabled' => false];
        }

        $today = now()->toDateString();
        $allChecks = $this->homeworkChecks->forStudent($studentId, 60);

        $todayChecks = $allChecks
            ->filter(fn (HomeworkCheck $check): bool => $check->checked_on?->toDateString() === $today)
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        [$weekFrom, $weekTo] = [
            now()->copy()->startOfWeek()->toDateString(),
            now()->copy()->endOfWeek()->toDateString(),
        ];

        $weekChecks = $allChecks
            ->filter(function (HomeworkCheck $check) use ($weekFrom, $weekTo): bool {
                $date = $check->checked_on?->toDateString();

                return $date !== null && $date >= $weekFrom && $date <= $weekTo;
            })
            ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
            ->values()
            ->all();

        $historyByDate = $allChecks
            ->groupBy(fn (HomeworkCheck $check): string => $check->checked_on?->toDateString() ?? 'unknown')
            ->map(function ($checks, string $date): array {
                $formatted = $checks
                    ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                    ->values()
                    ->all();

                return [
                    'date' => $date,
                    'checks' => $formatted,
                    'done_count' => collect($formatted)->where('status', HomeworkCheckStatus::Done->label())->count(),
                    'not_done_count' => collect($formatted)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
                ];
            })
            ->sortKeysDesc()
            ->values()
            ->all();

        $onReferencedDate = null;

        if (filled($referencedDate)) {
            $dateChecks = $allChecks
                ->filter(fn (HomeworkCheck $check): bool => $check->checked_on?->toDateString() === $referencedDate)
                ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                ->values()
                ->all();

            $onReferencedDate = [
                'date' => $referencedDate,
                'checks' => $dateChecks,
                'marked' => count($dateChecks) > 0,
                'done_count' => collect($dateChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'not_done_count' => collect($dateChecks)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
            ];
        }

        $assignments = [];

        if ($student) {
            $assignments = $this->homeworkAssignments->assignmentsForStudentProfile($student)
                ->map(fn (HomeworkAssignment $assignment): array => [
                    'title' => $assignment->title,
                    'batch' => $assignment->batch?->name,
                    'published_at' => $assignment->published_at?->toDateString(),
                    'viewed_on_portal' => $assignment->views->isNotEmpty(),
                ])
                ->values()
                ->all();
        }

        return [
            'enabled' => true,
            'today' => [
                'date' => $today,
                'checks' => $todayChecks,
                'marked_count' => count($todayChecks),
                'done_count' => collect($todayChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'not_done_count' => collect($todayChecks)->where('status', HomeworkCheckStatus::NotDone->label())->count(),
                'unmarked_today' => count($todayChecks) === 0,
            ],
            'this_week' => [
                'from' => $weekFrom,
                'to' => $weekTo,
                'not_done_count' => $this->homeworkChecks->notDoneCountThisWeek($studentId),
                'done_count' => collect($weekChecks)->where('status', HomeworkCheckStatus::Done->label())->count(),
                'checks' => $weekChecks,
            ],
            'on_referenced_date' => $onReferencedDate,
            'history_by_date' => $historyByDate,
            'recent_checks' => $allChecks
                ->take(30)
                ->map(fn (HomeworkCheck $check): array => $this->formatHomeworkCheck($check))
                ->values()
                ->all(),
            'portal_assignments' => $assignments,
        ];
    }

    /**
     * Exams tab — activity types with marks or attendance-only sessions.
     *
     * @return array<string, mixed>
     */
    protected function examsSnapshot(Student $student): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return ['enabled' => false];
        }

        if ($student->activeEnrollment === null) {
            return ['enabled' => false, 'reason' => 'not_enrolled'];
        }

        $types = ActivityType::query()->enabled()->ordered()->get();

        if ($types->isEmpty()) {
            return ['enabled' => true, 'activity_types' => []];
        }

        $activityTypes = $types->map(function (ActivityType $type) use ($student): array {
            if ($type->supportsScoring()) {
                $records = $this->activityAttendance->presentRecordsForStudent($student, $type)
                    ->take($this->listLimit());

                return [
                    'name' => $type->name,
                    'supports_scoring' => true,
                    'present_count' => $this->activityAttendance->presentCountForStudent($student, $type),
                    'records' => $records->map(function ($row): array {
                        $session = $row->attendable;

                        return [
                            'date' => $session?->session_date?->toDateString(),
                            'test_label' => $session?->title,
                            'batch' => $session?->batch?->name,
                            'marks_obtained' => $row->marks_obtained !== null ? (float) $row->marks_obtained : null,
                            'grade' => $row->grade,
                            'is_present' => (bool) $row->is_present,
                        ];
                    })->values()->all(),
                ];
            }

            $summary = $this->activityAttendance->attendanceSummaryForStudent($student, $type);
            $records = $this->activityAttendance->recordsForStudent($student, $type)
                ->take($this->listLimit());

            return [
                'name' => $type->name,
                'supports_scoring' => false,
                'summary' => $summary,
                'records' => $records->map(function ($row): array {
                    $session = $row->attendable;

                    return [
                        'date' => $session?->session_date?->toDateString(),
                        'session' => $session?->title,
                        'batch' => $session?->batch?->name,
                        'is_present' => (bool) $row->is_present,
                    ];
                })->values()->all(),
            ];
        })->values()->all();

        return [
            'enabled' => true,
            'activity_types' => $activityTypes,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function formatHomeworkCheck(HomeworkCheck $check): array
    {
        return [
            'date' => $check->checked_on?->toDateString(),
            'subject' => $check->subject_name,
            'topic' => $check->topic,
            'status' => $check->status->label(),
            'class' => $check->batch?->name,
        ];
    }

    protected function listLimit(): int
    {
        return max(5, (int) config('ask_crm.snapshot_list_limit', 25));
    }
}

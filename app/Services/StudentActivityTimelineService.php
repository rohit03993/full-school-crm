<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Enums\PaymentStatus;
use App\Models\Attendance;
use App\Models\BatchStudent;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\HomeworkCheck;
use App\Models\ParentFeeNotice;
use App\Models\Payment;
use App\Models\Student;
use App\Models\StudentCall;
use App\Models\StudentCase;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\FeeReminderWhatsAppTemplate;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use App\Support\HomeworkShareWhatsAppTemplate;
use App\Support\StudentPunchWhatsAppTemplate;
use App\Support\StudentWhatsAppThreadItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentActivityTimelineService
{
    public function __construct(
        protected StudentWhatsAppThreadService $whatsAppThread,
    ) {}

    /**
     * @return array{
     *     items: list<array{
     *         id: string,
     *         type: string,
     *         category: string,
     *         title: string,
     *         summary: ?string,
     *         detail: ?string,
     *         staff_name: ?string,
     *         occurred_at: Carbon,
     *         tab: ?string
     *     }>,
     *     has_more: bool,
     *     total_hint: int
     * }
     */
    public function forStudent(Student $student, ?User $viewer, int $limit = 40): array
    {
        $limit = max(10, min(200, $limit));
        $perSource = max($limit, 60);

        $items = collect()
            ->merge($this->calls($student, $viewer, $perSource))
            ->merge($this->visits($student, $viewer, $perSource))
            ->merge($this->cases($student, $viewer, $perSource))
            ->merge($this->payments($student, $viewer, $perSource))
            ->merge($this->parentFeeNotices($student, $viewer, $perSource))
            ->merge($this->whatsApp($student, $viewer, $perSource))
            ->merge($this->attendance($student, $viewer, $perSource))
            ->merge($this->homeworkChecks($student, $viewer, $perSource))
            ->merge($this->examMarks($student, $viewer, $perSource))
            ->merge($this->certificates($student, $viewer, $perSource))
            ->merge($this->documents($student, $viewer, $perSource))
            ->merge($this->enrollmentAndBatch($student, $viewer, $perSource))
            ->filter(fn (array $item): bool => $item['occurred_at'] instanceof Carbon)
            ->sortByDesc(fn (array $item): int => $item['occurred_at']->getTimestamp())
            ->values();

        $total = $items->count();

        return [
            'items' => $items->take($limit)->values()->all(),
            'has_more' => $total > $limit,
            'total_hint' => $total,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function calls(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Calls) || ! CrmAccess::can($viewer, CrmPermission::StudentsView)) {
            return [];
        }

        return StudentCall::query()
            ->where('student_id', $student->id)
            ->with('staff')
            ->orderByDesc('called_at')
            ->limit($limit)
            ->get()
            ->map(function (StudentCall $call): array {
                $purpose = $call->call_purpose?->label();
                $title = $purpose
                    ? 'Call · '.$purpose
                    : ($call->call_direction?->label() ?? 'Call').' call';

                $parts = array_filter([
                    $call->call_status?->label(),
                    $call->who_answered?->label(),
                ]);

                return $this->item(
                    id: 'call-'.$call->id,
                    type: 'call',
                    category: 'CALL',
                    title: $title,
                    summary: filled($call->call_notes) ? (string) $call->call_notes : null,
                    detail: $parts !== [] ? implode(' · ', $parts) : null,
                    staffName: $call->staff?->name,
                    at: $call->called_at ?? $call->created_at ?? now(),
                    tab: $call->student_case_id ? 'cases' : 'calls',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function visits(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            return [];
        }

        return Visit::query()
            ->where('student_id', $student->id)
            ->inPerson()
            ->with('staff')
            ->orderByDesc('visit_date')
            ->limit($limit)
            ->get()
            ->map(function (Visit $visit): array {
                $label = $visit->isCampusVisit() ? 'Campus visit' : 'Visit';

                return $this->item(
                    id: 'visit-'.$visit->id,
                    type: 'visit',
                    category: 'VISIT',
                    title: $label,
                    summary: filled($visit->discussion_summary) ? (string) $visit->discussion_summary : $visit->displayStatusLabel(),
                    detail: filled($visit->remarks) ? (string) $visit->remarks : $visit->displayStatusLabel(),
                    staffName: $visit->staff?->name,
                    at: Carbon::parse($visit->visit_date)->setTimeFromTimeString(
                        optional($visit->created_at)->format('H:i:s') ?? '12:00:00'
                    ),
                    tab: 'visits',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function cases(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Cases) || ! CrmAccess::can($viewer, CrmPermission::CasesView)) {
            return [];
        }

        $rows = [];

        $cases = StudentCase::query()
            ->where('student_id', $student->id)
            ->with(['openedBy', 'closedBy', 'currentAssignee'])
            ->orderByDesc('opened_at')
            ->limit($limit)
            ->get();

        foreach ($cases as $case) {
            $typeLabel = $case->case_type?->label();
            $titleBase = filled($case->title) ? (string) $case->title : ($typeLabel ?? 'Case');

            $rows[] = $this->item(
                id: 'case-open-'.$case->id,
                type: 'case',
                category: 'CASE',
                title: 'Case opened · '.$titleBase,
                summary: filled($case->summary) ? (string) $case->summary : ($case->case_number ? '#'.$case->case_number : null),
                detail: $typeLabel,
                staffName: $case->openedBy?->name ?? $case->currentAssignee?->name,
                at: $case->opened_at ?? $case->created_at ?? now(),
                tab: 'cases',
            );

            if ($case->closed_at) {
                $rows[] = $this->item(
                    id: 'case-close-'.$case->id,
                    type: 'case',
                    category: 'CASE',
                    title: 'Case closed · '.$titleBase,
                    summary: filled($case->closing_note) ? (string) $case->closing_note : null,
                    detail: $case->case_number ? '#'.$case->case_number : null,
                    staffName: $case->closedBy?->name,
                    at: $case->closed_at,
                    tab: 'cases',
                );
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function payments(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Fees) || ! CrmAccess::canViewFees($viewer)) {
            return [];
        }

        return Payment::query()
            ->where('student_id', $student->id)
            ->with('addedBy')
            ->orderByDesc('payment_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(function (Payment $payment): array {
                $amount = '₹'.number_format((float) $payment->amount, 2);
                $mode = $payment->payment_mode?->label();
                $receipt = $payment->receipt_number;

                if ($payment->status === PaymentStatus::Cancelled || $payment->cancelled_at) {
                    $title = 'Fee payment cancelled · '.$amount;
                    $at = $payment->cancelled_at
                        ?? ($payment->payment_date ? Carbon::parse($payment->payment_date)->endOfDay() : $payment->created_at)
                        ?? now();
                } else {
                    $title = 'Fee remitted · '.$amount;
                    $at = $payment->created_at
                        ?? ($payment->payment_date ? Carbon::parse($payment->payment_date)->setTime(12, 0) : now());
                }

                $detail = implode(' · ', array_filter([
                    $receipt ? 'Receipt '.$receipt : null,
                    $mode,
                    $payment->status?->label(),
                ]));

                return $this->item(
                    id: 'payment-'.$payment->id,
                    type: 'fee',
                    category: 'FEES',
                    title: $title,
                    summary: filled($payment->cancel_reason) ? (string) $payment->cancel_reason : null,
                    detail: $detail !== '' ? $detail : null,
                    staffName: $payment->addedBy?->name,
                    at: $at,
                    tab: 'fees',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function parentFeeNotices(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)
            || ! CrmAccess::can($viewer, CrmPermission::WhatsappFeeNotices)) {
            return [];
        }

        return ParentFeeNotice::query()
            ->where('student_id', $student->id)
            ->with('sentBy')
            ->orderByDesc('sent_at')
            ->limit($limit)
            ->get()
            ->map(function (ParentFeeNotice $notice): array {
                $amount = '₹'.number_format((float) $notice->amount, 2);
                $due = $notice->due_date
                    ? Carbon::parse($notice->due_date)->format('d M Y')
                    : null;

                return $this->item(
                    id: 'fee-notice-'.$notice->id,
                    type: 'whatsapp',
                    category: 'WHATSAPP',
                    title: 'Fee notice sent · '.$amount,
                    summary: $due ? 'Due '.$due : null,
                    detail: $notice->status?->label(),
                    staffName: $notice->sentBy?->name,
                    at: $notice->sent_at ?? $notice->created_at ?? now(),
                    tab: 'parent_updates',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function whatsApp(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return [];
        }

        if (! CrmAccess::canAny(
            $viewer,
            CrmPermission::WhatsappInbox,
            CrmPermission::WhatsappOps,
            CrmPermission::WhatsappCampaigns,
            CrmPermission::WhatsappFeeNotices,
        )) {
            return [];
        }

        $dedicatedFeeNotices = FeatureGate::enabled(LicenseFeature::WhatsApp)
            && CrmAccess::can($viewer, CrmPermission::WhatsappFeeNotices);

        return $this->whatsAppThread->threadForStudent($student, $limit)
            ->reject(function (StudentWhatsAppThreadItem $item) use ($dedicatedFeeNotices): bool {
                if (! $dedicatedFeeNotices || $item->isInbound()) {
                    return false;
                }

                // Avoid duplicate rows when Parent fee notices are listed separately.
                return $this->whatsAppKindLabel($item) === 'Fee reminder';
            })
            ->map(function (StudentWhatsAppThreadItem $item): array {
                $kind = $this->whatsAppKindLabel($item);
                $direction = $item->isInbound() ? 'Parent reply' : 'Message sent';
                $title = $kind ? $direction.' · '.$kind : $direction;

                $body = trim(Str::limit(strip_tags((string) $item->body), 160));

                return $this->item(
                    id: 'wa-'.$item->key,
                    type: 'whatsapp',
                    category: 'WHATSAPP',
                    title: $title,
                    summary: $body !== '' ? $body : null,
                    detail: implode(' · ', array_filter([
                        $item->templateName,
                        $item->statusLabel,
                    ])),
                    staffName: null,
                    at: $item->at ?? now(),
                    tab: 'messages',
                );
            })
            ->all();
    }

    protected function whatsAppKindLabel(StudentWhatsAppThreadItem $item): ?string
    {
        if ($item->isInbound()) {
            return null;
        }

        $name = (string) ($item->templateName ?? '');

        if ($name === '' && filled($item->body)) {
            // Campaign rows may embed template text only; sniff body keywords lightly.
            $body = strtolower((string) $item->body);
            if (str_contains($body, 'fee') || str_contains($body, 'pending amount')) {
                return 'Fee reminder';
            }
            if (str_contains($body, 'attendance') || str_contains($body, 'checked in') || str_contains($body, 'checked out')) {
                return 'Attendance';
            }
        }

        if ($name === '') {
            return null;
        }

        if (FeeReminderWhatsAppTemplate::looksLikeName($name)) {
            return 'Fee reminder';
        }

        if (StudentPunchWhatsAppTemplate::looksLikeInName($name)) {
            return 'Attendance IN';
        }

        if (StudentPunchWhatsAppTemplate::looksLikeOutName($name)) {
            return 'Attendance OUT';
        }

        if (HomeworkNotDoneWhatsAppTemplate::looksLikeName($name)
            || HomeworkShareWhatsAppTemplate::looksLikeName($name)) {
            return 'Homework';
        }

        if (str_contains(strtolower($name), 'fee')) {
            return 'Fee reminder';
        }

        return Str::limit(str_replace('_', ' ', $name), 40);
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function attendance(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Attendance)) {
            return [];
        }

        $rows = [];

        $records = Attendance::query()
            ->where('student_id', $student->id)
            ->with('markedBy')
            ->orderByDesc('attendance_date')
            ->limit((int) ceil($limit / 2))
            ->get();

        foreach ($records as $record) {
            if ($record->checked_in_at) {
                $rows[] = $this->item(
                    id: 'att-in-'.$record->id,
                    type: 'attendance',
                    category: 'ATTENDANCE',
                    title: 'Punch IN · '.($record->status?->label() ?? 'Present'),
                    summary: $record->leave_reason,
                    detail: $record->punch_source ? 'Source: '.$record->punch_source : null,
                    staffName: $record->markedBy?->name,
                    at: $record->checked_in_at,
                    tab: 'attendance',
                );
            }

            if ($record->checked_out_at) {
                $rows[] = $this->item(
                    id: 'att-out-'.$record->id,
                    type: 'attendance',
                    category: 'ATTENDANCE',
                    title: 'Punch OUT',
                    summary: null,
                    detail: $record->punch_source ? 'Source: '.$record->punch_source : null,
                    staffName: $record->markedBy?->name,
                    at: $record->checked_out_at,
                    tab: 'attendance',
                );
            }

            if (! $record->checked_in_at && ! $record->checked_out_at) {
                $rows[] = $this->item(
                    id: 'att-'.$record->id,
                    type: 'attendance',
                    category: 'ATTENDANCE',
                    title: 'Attendance · '.($record->status?->label() ?? 'Marked'),
                    summary: $record->leave_reason,
                    detail: null,
                    staffName: $record->markedBy?->name,
                    at: Carbon::parse($record->attendance_date)->setTime(12, 0),
                    tab: 'attendance',
                );
            }
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function homeworkChecks(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            return [];
        }

        return HomeworkCheck::query()
            ->where('student_id', $student->id)
            ->with('createdBy')
            ->orderByDesc('checked_on')
            ->limit($limit)
            ->get()
            ->map(function (HomeworkCheck $check): array {
                $status = $check->status instanceof HomeworkCheckStatus
                    ? $check->status->label()
                    : 'Checked';
                $subject = $check->subject_name ?: 'Homework';

                return $this->item(
                    id: 'hw-'.$check->id,
                    type: 'homework',
                    category: 'HOMEWORK',
                    title: 'Homework · '.$status.' ('.$subject.')',
                    summary: filled($check->topic) ? (string) $check->topic : null,
                    detail: $check->notify_status?->label(),
                    staffName: $check->createdBy?->name,
                    at: $check->notified_at
                        ?? ($check->checked_on ? Carbon::parse($check->checked_on)->setTime(12, 0) : $check->created_at)
                        ?? now(),
                    tab: 'homework',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function examMarks(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Marks)) {
            return [];
        }

        if (! CrmAccess::canAny($viewer, CrmPermission::MarksImport, CrmPermission::MarksPublish, CrmPermission::StudentsView)) {
            return [];
        }

        return $student->activityAttendances()
            ->with(['attendable.activityType'])
            ->orderByDesc('updated_at')
            ->limit($limit)
            ->get()
            ->filter(fn ($row): bool => $row->marks_obtained !== null || $row->grade !== null)
            ->map(function ($row): array {
                $session = $row->attendable;
                $type = $session?->activityType?->name ?? 'Exam';
                $title = $session?->title ? $type.' · '.$session->title : $type.' marks';
                $score = $row->marks_obtained !== null
                    ? 'Marks: '.$row->marks_obtained
                    : ($row->grade ? 'Grade: '.$row->grade : null);

                $at = $session?->session_date
                    ? Carbon::parse($session->session_date)->setTime(12, 0)
                    : ($row->updated_at ?? $row->created_at ?? now());

                return $this->item(
                    id: 'marks-'.$row->id,
                    type: 'exam',
                    category: 'EXAMS',
                    title: $title,
                    summary: $score,
                    detail: filled($row->remarks) ? (string) $row->remarks : null,
                    staffName: null,
                    at: $at,
                    tab: 'activities',
                );
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function certificates(Student $student, ?User $viewer, int $limit): array
    {
        if (! FeatureGate::enabled(LicenseFeature::Certificates)
            || ! CrmAccess::can($viewer, CrmPermission::CertificatesView)) {
            return [];
        }

        return StudentCertificate::query()
            ->where('student_id', $student->id)
            ->with('issuedBy')
            ->orderByDesc('issued_on')
            ->limit($limit)
            ->get()
            ->map(function (StudentCertificate $cert): array {
                return $this->item(
                    id: 'cert-'.$cert->id,
                    type: 'certificate',
                    category: 'CERTIFICATE',
                    title: 'Certificate issued · '.($cert->type?->label() ?? 'Certificate'),
                    summary: $cert->serial_number ? 'Serial '.$cert->serial_number : null,
                    detail: filled($cert->remarks) ? (string) $cert->remarks : null,
                    staffName: $cert->issuedBy?->name,
                    at: $cert->issued_on
                        ? Carbon::parse($cert->issued_on)->setTime(12, 0)
                        : ($cert->created_at ?? now()),
                    tab: 'certificates',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function documents(Student $student, ?User $viewer, int $limit): array
    {
        $ids = \App\Models\Admission::query()
            ->where('student_id', $student->id)
            ->pluck('id');

        if ($ids->isEmpty()) {
            return [];
        }

        return Document::query()
            ->where('documentable_type', \App\Models\Admission::class)
            ->whereIn('documentable_id', $ids)
            ->with('uploadedBy')
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get()
            ->map(function (Document $doc): array {
                return $this->item(
                    id: 'doc-'.$doc->id,
                    type: 'document',
                    category: 'DOCUMENT',
                    title: 'Document uploaded · '.($doc->type?->label() ?? 'File'),
                    summary: $doc->original_filename,
                    detail: null,
                    staffName: $doc->uploadedBy?->name,
                    at: $doc->created_at ?? now(),
                    tab: 'documents',
                );
            })
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function enrollmentAndBatch(Student $student, ?User $viewer, int $limit): array
    {
        $rows = [];

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with('course')
            ->orderByDesc('enrolled_at')
            ->limit($limit)
            ->get();

        foreach ($enrollments as $enrollment) {
            if (! $enrollment->enrolled_at) {
                continue;
            }

            $rows[] = $this->item(
                id: 'enroll-'.$enrollment->id,
                type: 'enrollment',
                category: 'ENROLLMENT',
                title: 'Enrolled · '.($enrollment->course?->name ?? 'Course'),
                summary: $enrollment->enrollment_number
                    ? 'Roll '.$enrollment->enrollment_number
                    : null,
                detail: $enrollment->status?->label(),
                staffName: null,
                at: Carbon::parse($enrollment->enrolled_at),
                tab: 'overview',
            );
        }

        $batches = BatchStudent::query()
            ->where('student_id', $student->id)
            ->with(['batch', 'assignedBy'])
            ->orderByDesc('assigned_at')
            ->limit($limit)
            ->get();

        foreach ($batches as $row) {
            if (! $row->assigned_at) {
                continue;
            }

            $rows[] = $this->item(
                id: 'batch-'.$row->id,
                type: 'batch',
                category: 'BATCH',
                title: 'Batch assigned · '.($row->batch?->name ?? 'Batch'),
                summary: $row->is_active ? 'Active' : 'Inactive',
                detail: null,
                staffName: $row->assignedBy?->name,
                at: Carbon::parse($row->assigned_at),
                tab: 'overview',
            );
        }

        return $rows;
    }

    /**
     * @return array{
     *     id: string,
     *     type: string,
     *     category: string,
     *     title: string,
     *     summary: ?string,
     *     detail: ?string,
     *     staff_name: ?string,
     *     occurred_at: Carbon,
     *     tab: ?string
     * }
     */
    protected function item(
        string $id,
        string $type,
        string $category,
        string $title,
        ?string $summary,
        ?string $detail,
        ?string $staffName,
        Carbon $at,
        ?string $tab,
    ): array {
        return [
            'id' => $id,
            'type' => $type,
            'category' => $category,
            'title' => $title,
            'summary' => filled($summary) ? $summary : null,
            'detail' => filled($detail) ? $detail : null,
            'staff_name' => filled($staffName) ? $staffName : null,
            'occurred_at' => $at,
            'tab' => $tab,
        ];
    }
}

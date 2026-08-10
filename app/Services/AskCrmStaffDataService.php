<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\StudentCaseStatus;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\MyTeachingAssignmentsPage;
use App\Models\StudentCase;
use App\Models\User;
use App\Models\VisitMeetingAssignment;
use App\Support\CrmAccess;
use App\Support\FeatureGate;

class AskCrmStaffDataService
{
    public function __construct(
        protected CallQueueService $callQueue,
        protected MyLeadsService $myLeads,
        protected FollowUpWorklistService $followUps,
        protected VisitMeetingAssignmentService $meetings,
        protected StudentCaseService $cases,
        protected ExamWindowService $examWindows,
        protected FeesDashboardService $feesDashboard,
    ) {}

    /**
     * Read-only snapshot of the signed-in staff member's CRM work queues.
     *
     * @return array<string, mixed>
     */
    public function myTasksSnapshot(User $user): array
    {
        $canCall = CrmAccess::can($user, CrmPermission::LeadsCall)
            || CrmAccess::can($user, CrmPermission::LeadsViewAssigned);
        $canCases = CrmAccess::can($user, CrmPermission::CasesView);
        $canFees = CrmAccess::canViewFees($user) && FeatureGate::enabled(LicenseFeature::Fees);

        $counts = [
            'call_queue_due' => 0,
            'call_queue_scheduled' => 0,
            'calls_today' => 0,
            'connected_today' => 0,
            'leads_uncalled' => 0,
            'leads_due_followups' => 0,
            'followups_due_total' => 0,
            'meetings_open' => 0,
            'cases_open' => 0,
            'exam_marks_pending' => 0,
        ];

        $callQueue = [];
        $meetingsOpen = [];
        $casesOpen = [];
        $teachingPending = [];
        $feesInstitute = ['enabled' => false];

        if ($canCall) {
            $stats = $this->callQueue->todayStats($user);
            $leadStats = $this->myLeads->stats($user);

            $counts['call_queue_due'] = (int) ($stats['queue_count'] ?? 0);
            $counts['call_queue_scheduled'] = (int) ($stats['scheduled_count'] ?? 0);
            $counts['calls_today'] = (int) ($stats['calls_today'] ?? 0);
            $counts['connected_today'] = (int) ($stats['connected_today'] ?? 0);
            $counts['leads_uncalled'] = (int) ($leadStats['uncalled'] ?? 0);
            $counts['leads_due_followups'] = (int) ($leadStats['due_call_followups'] ?? 0);
            $counts['followups_due_total'] = $this->followUps->totalDueCount($user);

            $callQueue = $this->callQueue->dueQueue($user, 8)
                ->map(function ($student): array {
                    $payload = $this->callQueue->leadPayload($student);

                    return [
                        'id' => $student->id,
                        'name' => $student->name,
                        'mobile_display' => $payload['mobile_display'] ?? '—',
                        'follow_up_queue_label' => $payload['follow_up_queue_label'] ?? null,
                        'is_overdue' => (bool) ($payload['is_overdue'] ?? false),
                        'is_due_today' => (bool) ($payload['is_due_today'] ?? false),
                        'profile_url' => $payload['profile_url'] ?? null,
                    ];
                })
                ->values()
                ->all();
        }

        $counts['meetings_open'] = $this->meetings->openCountForStaff($user);
        $meetingsOpen = VisitMeetingAssignment::query()
            ->where('assigned_to_user_id', $user->id)
            ->where('status', VisitMeetingAssignmentStatus::Open)
            ->with(['student', 'enquiry'])
            ->latest('created_at')
            ->limit(8)
            ->get()
            ->map(fn (VisitMeetingAssignment $row): array => [
                'id' => $row->id,
                'student_id' => $row->student_id,
                'student_name' => $row->student?->name,
                'enquiry_number' => $row->enquiry?->enquiry_number,
                'handoff_notes' => $row->handoff_notes,
                'created_at' => $row->created_at?->format('d M Y'),
            ])
            ->all();

        if ($canCases) {
            $counts['cases_open'] = $this->cases->openCountForAssignee($user);
            $casesOpen = StudentCase::query()
                ->where('current_assignee_user_id', $user->id)
                ->where('status', StudentCaseStatus::Open)
                ->with('student')
                ->latest('opened_at')
                ->limit(8)
                ->get()
                ->map(fn (StudentCase $case): array => [
                    'id' => $case->id,
                    'case_number' => $case->case_number,
                    'title' => $case->title,
                    'case_type' => $case->case_type?->label() ?? (string) $case->case_type,
                    'student_id' => $case->student_id,
                    'student_name' => $case->student?->name,
                    'opened_at' => $case->opened_at?->format('d M Y'),
                ])
                ->all();
        }

        $pendingMarks = $this->examWindows->pendingEntriesForUser($user);
        $counts['exam_marks_pending'] = count($pendingMarks);
        $teachingPending = collect($pendingMarks)
            ->take(8)
            ->map(function (array $row): array {
                $window = $row['window'] ?? null;
                $subject = $row['subject'] ?? null;

                return [
                    'window_id' => $window?->id,
                    'test_name' => $window?->activityType?->name ?? $window?->name,
                    'subject' => $subject?->courseSubject?->name ?? $subject?->subject_name,
                    'batch' => $window?->batch?->name,
                    'session_date' => $window?->session_date?->format('d M Y'),
                ];
            })
            ->values()
            ->all();

        if ($canFees) {
            $summary = $this->feesDashboard->summary();
            $feesInstitute = [
                'enabled' => true,
                'overdue_students_count' => (int) ($summary['overdue_students_count'] ?? 0),
                'overdue_amount' => (float) ($summary['overdue_amount'] ?? 0),
                'pending_fees_total' => (float) ($summary['pending_fees_total'] ?? 0),
            ];
        }

        return [
            'meta' => [
                'today' => now()->toDateString(),
                'source' => 'staff_my_work',
                'user_id' => $user->id,
                'user_name' => $user->name,
            ],
            'counts' => $counts,
            'call_queue' => $callQueue,
            'meetings_open' => $meetingsOpen,
            'cases_open' => $casesOpen,
            'teaching' => [
                'pending_mark_entries' => $teachingPending,
            ],
            'fees_institute' => $feesInstitute,
            'links' => [
                'my_work' => MyMeetingsPage::getUrl(),
                'call_queue' => CallQueuePage::getUrl(),
                'my_leads' => MyLeadsPage::getUrl(),
                'follow_ups' => FollowUpsPage::getUrl(),
                'my_classes' => MyTeachingAssignmentsPage::getUrl(),
            ],
            'permissions' => [
                'can_call' => $canCall,
                'can_cases' => $canCases,
                'can_fees' => $canFees,
            ],
        ];
    }
}

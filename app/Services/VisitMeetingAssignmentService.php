<?php

namespace App\Services;

use App\Enums\CampusVisitOutcome;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitMeetingAssignment;
use App\Support\CrmCacheInvalidator;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitMeetingAssignmentService
{
    public function __construct(
        protected AuditService $audit,
        protected VisitService $visits,
    ) {}

    public function assign(
        Student $student,
        ?Enquiry $enquiry,
        User $assignedTo,
        User $assignedBy,
        ?string $handoffNotes = null,
    ): VisitMeetingAssignment {
        if (! $assignedTo->is_active) {
            throw ValidationException::withMessages([
                'meeting_assign_to_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        if ($this->openForStudent($student)) {
            throw ValidationException::withMessages([
                'assign_meeting' => 'This student already has an open meeting assignment. Complete it before assigning again.',
            ]);
        }

        return DB::transaction(function () use ($student, $enquiry, $assignedTo, $assignedBy, $handoffNotes): VisitMeetingAssignment {
            $assignment = VisitMeetingAssignment::query()->create([
                'student_id' => $student->id,
                'enquiry_id' => $enquiry?->id,
                'assigned_to_user_id' => $assignedTo->id,
                'assigned_by_user_id' => $assignedBy->id,
                'handoff_notes' => filled($handoffNotes) ? trim($handoffNotes) : null,
                'status' => VisitMeetingAssignmentStatus::Open,
            ]);

            $this->audit->log(
                action: 'Meeting Assigned',
                auditable: $assignment,
                newValues: [
                    'student_id' => $student->id,
                    'assigned_to_user_id' => $assignedTo->id,
                ],
                user: $assignedBy,
            );

            $this->notifyAssignedStaff($assignment);
            $this->flushCaches($assignedTo->id);

            return $assignment->load(['student', 'enquiry.course', 'assignedTo', 'assignedBy']);
        });
    }

    public function close(
        VisitMeetingAssignment $assignment,
        User $closedBy,
        string $meetingNotes,
        ?VisitStatus $visitStatus = null,
        ?CampusVisitOutcome $campusOutcome = null,
        ?string $remarks = null,
    ): VisitMeetingAssignment {
        if (! $assignment->isOpen()) {
            throw ValidationException::withMessages([
                'meeting_notes' => 'This meeting assignment is already closed.',
            ]);
        }

        if ($closedBy->id !== $assignment->assigned_to_user_id) {
            throw ValidationException::withMessages([
                'meeting_notes' => 'Only the assigned staff member can close this meeting.',
            ]);
        }

        $notes = trim($meetingNotes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'meeting_notes' => 'Meeting notes are required before closing.',
            ]);
        }

        return DB::transaction(function () use ($assignment, $closedBy, $notes, $visitStatus, $campusOutcome, $remarks): VisitMeetingAssignment {
            $assignment->loadMissing(['student.activeEnrollment', 'enquiry']);

            $enquiry = $assignment->enquiry ?? $assignment->student->enquiries()->latest()->first();
            $isEnrolled = $assignment->student->activeEnrollment !== null;

            if ($isEnrolled) {
                if (! $campusOutcome) {
                    throw ValidationException::withMessages([
                        'close_meeting_campus_outcome' => 'Select how this meeting was resolved.',
                    ]);
                }

                $visit = $this->visits->recordCampusClose(
                    $assignment->student,
                    $enquiry,
                    $closedBy,
                    $notes,
                    $campusOutcome,
                    $remarks,
                );
            } else {
                if (! $visitStatus) {
                    throw ValidationException::withMessages([
                        'close_meeting_status' => 'Select a visit outcome.',
                    ]);
                }

                $visit = null;

                if ($enquiry) {
                    $visit = Visit::query()->create([
                        'student_id' => $assignment->student_id,
                        'enquiry_id' => $enquiry->id,
                        'visit_date' => now()->toDateString(),
                        'staff_user_id' => $closedBy->id,
                        'discussion_summary' => $notes,
                        'remarks' => $remarks,
                        'status' => $visitStatus,
                    ]);

                    $enquiry->update(['latest_visit_status' => $visitStatus]);
                }
            }

            $assignment->update([
                'meeting_notes' => $notes,
                'status' => VisitMeetingAssignmentStatus::Closed,
                'closed_at' => now(),
                'closed_by_user_id' => $closedBy->id,
                'resulting_visit_id' => $visit?->id,
            ]);

            $this->audit->log(
                action: 'Meeting Closed',
                auditable: $assignment,
                newValues: [
                    'student_id' => $assignment->student_id,
                    'visit_id' => $visit?->id,
                ],
                user: $closedBy,
            );

            $this->flushCaches($assignment->assigned_to_user_id);

            return $assignment->fresh(['student', 'enquiry.course', 'assignedTo', 'assignedBy', 'resultingVisit']);
        });
    }

    public function openForStudent(Student $student): ?VisitMeetingAssignment
    {
        return VisitMeetingAssignment::query()
            ->where('student_id', $student->id)
            ->where('status', VisitMeetingAssignmentStatus::Open)
            ->with(['assignedTo', 'assignedBy', 'enquiry.course'])
            ->latest('id')
            ->first();
    }

    /**
     * @return array{
     *     id: int,
     *     staff_name: string,
     *     assigned_by_name: string,
     *     assigned_at: \Illuminate\Support\Carbon,
     *     handoff_notes: ?string,
     *     is_mine: bool,
     *     can_close: bool,
     *     course_name: ?string,
     * }|null
     */
    public function profileMeetingAssignment(Student $student, ?User $viewer): ?array
    {
        $assignment = $this->openForStudent($student);

        if (! $assignment) {
            return null;
        }

        $isMine = $viewer && $assignment->assigned_to_user_id === $viewer->id;
        $isAssigner = $viewer && $assignment->assigned_by_user_id === $viewer->id;

        if (! $isMine && ! $isAssigner) {
            return null;
        }

        return [
            'id' => $assignment->id,
            'staff_name' => $assignment->assignedTo?->name ?? 'Staff',
            'assigned_by_name' => $assignment->assignedBy?->name ?? 'Staff',
            'assigned_at' => $assignment->created_at,
            'handoff_notes' => $assignment->handoff_notes,
            'is_mine' => $isMine,
            'can_close' => $isMine,
            'course_name' => $assignment->enquiry?->course?->name,
        ];
    }

    /**
     * @return array{open: int, closed: int, total: int}
     */
    public function statsForStaff(User $staff): array
    {
        $base = VisitMeetingAssignment::query()->where('assigned_to_user_id', $staff->id);

        $open = (clone $base)->where('status', VisitMeetingAssignmentStatus::Open)->count();
        $closed = (clone $base)->where('status', VisitMeetingAssignmentStatus::Closed)->count();

        return [
            'open' => $open,
            'closed' => $closed,
            'total' => $open + $closed,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, VisitMeetingAssignment>
     */
    public function paginateForStaff(
        User $staff,
        string $statusFilter = 'open',
        ?string $search = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = VisitMeetingAssignment::query()
            ->where('assigned_to_user_id', $staff->id)
            ->with(['student.activeEnrollment', 'enquiry.course', 'assignedBy', 'resultingVisit']);

        if ($statusFilter === 'closed') {
            $query->where('status', VisitMeetingAssignmentStatus::Closed)->orderByDesc('closed_at');
        } else {
            $query->where('status', VisitMeetingAssignmentStatus::Open)->orderByDesc('created_at');
        }

        if (filled($search)) {
            $term = trim($search);

            $query->where(function ($inner) use ($term): void {
                $inner->whereHas('student', function ($studentQuery) use ($term): void {
                    $studentQuery->where('name', 'like', '%'.$term.'%')
                        ->orWhere('mobile', 'like', '%'.preg_replace('/\D/', '', $term).'%');
                })->orWhereHas('enquiry', function ($enquiryQuery) use ($term): void {
                    $enquiryQuery->where('enquiry_number', 'like', '%'.strtoupper($term).'%');
                });
            });
        }

        return $query->paginate(
            $perPage ?? \App\Support\CrmPagination::PER_PAGE,
            ['*'],
            'page',
            $page,
        );
    }

    /**
     * @return LengthAwarePaginator<int, VisitMeetingAssignment>
     */
    public function paginateOpenForStaff(
        User $staff,
        ?string $search = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        return $this->paginateForStaff($staff, 'open', $search, $perPage, $page);
    }

    public function openCountForStaff(User $staff): int
    {
        return VisitMeetingAssignment::query()
            ->where('assigned_to_user_id', $staff->id)
            ->where('status', VisitMeetingAssignmentStatus::Open)
            ->count();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function assignFromFormData(Student $student, ?Enquiry $enquiry, User $assignedBy, array $data): ?VisitMeetingAssignment
    {
        if (! ($data['assign_meeting'] ?? false)) {
            return null;
        }

        $staffId = (int) ($data['meeting_assign_to_user_id'] ?? 0);

        if ($staffId <= 0) {
            throw ValidationException::withMessages([
                'meeting_assign_to_user_id' => 'Select a staff member for the meeting.',
            ]);
        }

        $handoff = $data['meeting_handoff_notes']
            ?? $data['discussion_summary']
            ?? null;

        return $this->assign(
            $student,
            $enquiry,
            User::query()->findOrFail($staffId),
            $assignedBy,
            is_string($handoff) ? $handoff : null,
        );
    }

    protected function notifyAssignedStaff(VisitMeetingAssignment $assignment): void
    {
        $assignment->loadMissing(['student', 'enquiry', 'assignedTo', 'assignedBy']);

        $recipient = $assignment->assignedTo;

        if (! $recipient) {
            return;
        }

        $studentName = $assignment->student?->name ?? 'Student';
        $assignerName = $assignment->assignedBy?->name ?? 'Staff';
        $profileUrl = StudentProfilePage::getUrl(['record' => $assignment->student_id]);

        Notification::make()
            ->title('Campus meeting assigned to you')
            ->body("{$assignerName} assigned {$studentName} for a meeting. Open the profile to view handoff notes.")
            ->icon('heroicon-o-user-group')
            ->actions([
                \Filament\Actions\Action::make('view')
                    ->label('Open profile')
                    ->url($profileUrl),
            ])
            ->sendToDatabase($recipient);
    }

    protected function flushCaches(int $staffUserId): void
    {
        CrmCacheInvalidator::afterMeetingAssignmentChange($staffUserId);
    }
}

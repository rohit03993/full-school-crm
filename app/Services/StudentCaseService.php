<?php

namespace App\Services;

use App\Enums\CampusVisitPurpose;
use App\Enums\CrmPermission;
use App\Enums\NumberSequenceType;
use App\Enums\RoleName;
use App\Enums\StudentCaseStatus;
use App\Enums\VisitMeetingAssignmentStatus;
use App\Filament\Pages\MyMeetingsPage;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Student;
use App\Models\StudentCase;
use App\Models\StudentCaseAssignment;
use App\Models\StudentCaseNote;
use App\Models\StudentCaseRevision;
use App\Models\User;
use App\Models\Visit;
use App\Models\VisitMeetingAssignment;
use App\Support\CrmAccess;
use App\Support\CrmNavBadges;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class StudentCaseService
{
    public function __construct(
        protected NumberGeneratorService $numbers,
        protected AuditService $audit,
    ) {}

    public function open(
        Student $student,
        CampusVisitPurpose $caseType,
        string $title,
        ?string $summary,
        User $assignee,
        User $openedBy,
        ?string $handoffNote = null,
        ?Visit $visit = null,
    ): StudentCase {
        if ($student->activeEnrollment()->doesntExist()) {
            throw ValidationException::withMessages([
                'student' => 'Cases can only be opened for enrolled students.',
            ]);
        }

        if (! $assignee->is_active) {
            throw ValidationException::withMessages([
                'assignee_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        $title = trim($title);
        $handoffNote = filled($handoffNote) ? trim($handoffNote) : null;

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Case title is required.',
            ]);
        }

        return DB::transaction(function () use (
            $student,
            $caseType,
            $title,
            $summary,
            $assignee,
            $openedBy,
            $handoffNote,
            $visit,
        ): StudentCase {
            $case = StudentCase::query()->create([
                'case_number' => $this->numbers->generate(NumberSequenceType::StudentCase),
                'student_id' => $student->id,
                'visit_id' => $visit?->id,
                'case_type' => $caseType,
                'status' => StudentCaseStatus::Open,
                'title' => $title,
                'summary' => filled($summary) ? trim($summary) : null,
                'opened_by_user_id' => $openedBy->id,
                'current_assignee_user_id' => $assignee->id,
                'opened_at' => now(),
            ]);

            StudentCaseAssignment::query()->create([
                'student_case_id' => $case->id,
                'from_user_id' => null,
                'to_user_id' => $assignee->id,
                'assigned_by_user_id' => $openedBy->id,
                'note' => $handoffNote ?? 'Case opened.',
            ]);

            if ($visit && $visit->student_case_id === null) {
                $visit->update(['student_case_id' => $case->id]);
            }

            $this->audit->log(
                action: 'Case Opened',
                auditable: $case,
                newValues: [
                    'student_id' => $student->id,
                    'case_number' => $case->case_number,
                    'assignee_user_id' => $assignee->id,
                ],
                user: $openedBy,
            );

            $this->notifyAssignee($case->fresh(['student']), $assignee, $openedBy);
            $this->flushNavBadges($assignee->id, $openedBy->id);

            return $case->fresh([
                'student',
                'visit',
                'openedBy',
                'currentAssignee',
                'assignments.toUser',
                'assignments.assignedBy',
            ]);
        });
    }

    public function transfer(
        StudentCase $case,
        User $assignee,
        User $assignedBy,
        string $note,
    ): StudentCase {
        if (! $case->isOpen()) {
            throw ValidationException::withMessages([
                'case' => 'This case is already closed.',
            ]);
        }

        if (! $assignee->is_active) {
            throw ValidationException::withMessages([
                'assignee_user_id' => 'Selected staff account is inactive.',
            ]);
        }

        if (! $this->canTransfer($case, $assignedBy)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to transfer this case.',
            ]);
        }

        $note = trim($note);

        if ($note === '') {
            throw ValidationException::withMessages([
                'note' => 'A transfer note is required.',
            ]);
        }

        if ($case->current_assignee_user_id === $assignee->id) {
            throw ValidationException::withMessages([
                'assignee_user_id' => 'This case is already assigned to the selected staff member.',
            ]);
        }

        return DB::transaction(function () use ($case, $assignee, $assignedBy, $note): StudentCase {
            $fromUserId = $case->current_assignee_user_id;

            $case->update([
                'current_assignee_user_id' => $assignee->id,
            ]);

            StudentCaseAssignment::query()->create([
                'student_case_id' => $case->id,
                'from_user_id' => $fromUserId,
                'to_user_id' => $assignee->id,
                'assigned_by_user_id' => $assignedBy->id,
                'note' => $note,
            ]);

            $this->audit->log(
                action: 'Case Transferred',
                auditable: $case,
                newValues: [
                    'from_user_id' => $fromUserId,
                    'to_user_id' => $assignee->id,
                ],
                user: $assignedBy,
            );

            $this->notifyAssignee($case->fresh(['student']), $assignee, $assignedBy);
            $this->flushNavBadges($assignee->id, $fromUserId, $assignedBy->id);

            return $case->fresh([
                'currentAssignee',
                'assignments.toUser',
                'assignments.fromUser',
                'assignments.assignedBy',
            ]);
        });
    }

    public function close(StudentCase $case, User $closedBy, string $closingNote): StudentCase
    {
        if (! $case->isOpen()) {
            throw ValidationException::withMessages([
                'case' => 'This case is already closed.',
            ]);
        }

        if (! $this->canClose($case, $closedBy)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to close this case.',
            ]);
        }

        $closingNote = trim($closingNote);

        if ($closingNote === '') {
            throw ValidationException::withMessages([
                'closing_note' => 'A closing note is required.',
            ]);
        }

        return DB::transaction(function () use ($case, $closedBy, $closingNote): StudentCase {
            $case->update([
                'status' => StudentCaseStatus::Closed,
                'closed_by_user_id' => $closedBy->id,
                'closing_note' => $closingNote,
                'closed_at' => now(),
            ]);

            $this->audit->log(
                action: 'Case Closed',
                auditable: $case,
                newValues: [
                    'case_number' => $case->case_number,
                    'closing_note' => $closingNote,
                ],
                user: $closedBy,
            );

            $this->flushNavBadges($case->current_assignee_user_id, $closedBy->id);

            return $case->fresh(['closedBy', 'currentAssignee', 'openedBy']);
        });
    }

    public function addUpdate(StudentCase $case, User $author, string $body, bool $fromMeeting = false): StudentCaseNote
    {
        if (! $case->isOpen()) {
            throw ValidationException::withMessages([
                'case' => 'This case is already closed. Reopen it before adding a meeting note.',
            ]);
        }

        if (! $this->canAddUpdate($case, $author, $fromMeeting)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to add a note on this case.',
            ]);
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'Write what was spoken before saving.',
            ]);
        }

        return DB::transaction(function () use ($case, $author, $body): StudentCaseNote {
            $note = StudentCaseNote::query()->create([
                'student_case_id' => $case->id,
                'user_id' => $author->id,
                'kind' => StudentCaseNote::KIND_UPDATE,
                'body' => $body,
            ]);

            $this->audit->log(
                action: 'Case Note Added',
                auditable: $case,
                newValues: [
                    'note_id' => $note->id,
                    'body' => $body,
                ],
                user: $author,
            );

            return $note->fresh('author');
        });
    }

    public function updateNote(StudentCaseNote $note, User $editor, string $body): StudentCaseNote
    {
        $note->loadMissing('studentCase');
        $case = $note->studentCase;

        if (! $case || ! $this->canEditNote($case, $note, $editor)) {
            throw ValidationException::withMessages([
                'body' => 'You are not allowed to edit this note.',
            ]);
        }

        $body = trim($body);

        if ($body === '') {
            throw ValidationException::withMessages([
                'body' => 'The note cannot be empty.',
            ]);
        }

        $previous = $note->body;

        return DB::transaction(function () use ($note, $case, $editor, $body, $previous): StudentCaseNote {
            $note->update(['body' => $body]);

            $this->audit->log(
                action: 'Case Note Edited',
                auditable: $case,
                oldValues: ['body' => $previous],
                newValues: [
                    'note_id' => $note->id,
                    'body' => $body,
                ],
                user: $editor,
            );

            if ($this->textChanged($previous, $body)) {
                $this->storeRevision($case, $editor, StudentCaseRevision::STATUS_APPLIED, [
                    'note_changed' => true,
                    'note_id' => $note->id,
                    'old_note' => $previous,
                    'new_note' => $body,
                ]);
            }

            return $note->fresh('author');
        });
    }

    public function updateDetails(StudentCase $case, User $editor, string $title, ?string $summary, ?string $closingNote = null): StudentCase
    {
        if (! $this->canEditDetails($case, $editor)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to edit this case.',
            ]);
        }

        $title = trim($title);
        $summary = filled($summary) ? trim($summary) : null;

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Case title is required.',
            ]);
        }

        $closingNote = $case->isOpen() ? $case->closing_note : (filled($closingNote) ? trim((string) $closingNote) : null);

        if (! $case->isOpen() && blank($closingNote)) {
            throw ValidationException::withMessages([
                'closing_note' => 'A closing note is required.',
            ]);
        }

        return DB::transaction(function () use ($case, $editor, $title, $summary, $closingNote): StudentCase {
            $previous = [
                'title' => $case->title,
                'summary' => $case->summary,
                'closing_note' => $case->closing_note,
            ];

            $case->update([
                'title' => $title,
                'summary' => $summary,
                'closing_note' => $closingNote,
            ]);

            $this->storeRevision($case, $editor, StudentCaseRevision::STATUS_APPLIED, $this->revisionPayload($previous, $title, $summary, $closingNote));

            $this->audit->log(
                action: 'Case Edited',
                auditable: $case,
                oldValues: $previous,
                newValues: [
                    'title' => $title,
                    'summary' => $summary,
                    'closing_note' => $closingNote,
                ],
                user: $editor,
            );

            return $case->fresh(['closedBy', 'currentAssignee', 'openedBy']);
        });
    }

    public function reopen(StudentCase $case, User $user): StudentCase
    {
        if ($case->isOpen()) {
            throw ValidationException::withMessages([
                'case' => 'This case is already open.',
            ]);
        }

        if (! $this->canReopen($case, $user)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to reopen this case.',
            ]);
        }

        return DB::transaction(function () use ($case, $user): StudentCase {
            $closingNote = $case->closing_note;

            if (filled($closingNote)) {
                StudentCaseNote::query()->create([
                    'student_case_id' => $case->id,
                    'user_id' => $user->id,
                    'kind' => StudentCaseNote::KIND_REOPEN,
                    'body' => $closingNote,
                ]);
            }

            $case->update([
                'status' => StudentCaseStatus::Open,
                'closed_by_user_id' => null,
                'closing_note' => null,
                'closed_at' => null,
            ]);

            $this->audit->log(
                action: 'Case Reopened',
                auditable: $case,
                oldValues: ['closing_note' => $closingNote],
                newValues: ['status' => StudentCaseStatus::Open->value],
                user: $user,
            );

            $this->flushNavBadges($case->current_assignee_user_id, $user->id);

            return $case->fresh(['closedBy', 'currentAssignee', 'openedBy', 'notes.author']);
        });
    }

    public function requestEdit(StudentCase $case, User $requester, string $title, ?string $summary, ?string $closingNote = null): StudentCaseRevision
    {
        if (! $this->canRequestEdit($case, $requester)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to request an edit on this case.',
            ]);
        }

        if ($this->pendingRevision($case)) {
            throw ValidationException::withMessages([
                'case' => 'An edit is already waiting for admin.',
            ]);
        }

        $title = trim($title);
        $summary = filled($summary) ? trim($summary) : null;
        $closingNote = $case->isOpen() ? $case->closing_note : (filled($closingNote) ? trim((string) $closingNote) : $case->closing_note);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => 'Case title is required.',
            ]);
        }

        $previous = [
            'title' => $case->title,
            'summary' => $case->summary,
            'closing_note' => $case->closing_note,
        ];
        $payload = $this->revisionPayload($previous, $title, $summary, $closingNote);

        if ($payload === []) {
            throw ValidationException::withMessages([
                'summary' => 'Change the remark before asking admin to approve it.',
            ]);
        }

        return DB::transaction(function () use ($case, $requester, $payload, $previous): StudentCaseRevision {
            $revision = $this->storeRevision($case, $requester, StudentCaseRevision::STATUS_PENDING, $payload);

            $this->audit->log(
                action: 'Case Edit Requested',
                auditable: $case,
                oldValues: $previous,
                newValues: $payload,
                user: $requester,
            );

            return $revision->fresh('author');
        });
    }

    public function acceptRevision(StudentCaseRevision $revision, User $reviewer): StudentCase
    {
        $revision->loadMissing('studentCase');
        $case = $revision->studentCase;

        if (! $case || ! $revision->isPending() || ! $this->canReviewRevision($case, $reviewer)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to approve this edit.',
            ]);
        }

        return DB::transaction(function () use ($revision, $case, $reviewer): StudentCase {
            $updates = [];

            if ($revision->title_changed) {
                $updates['title'] = $revision->new_title;
            }

            if ($revision->summary_changed) {
                $updates['summary'] = $revision->new_summary;
            }

            if ($revision->closing_note_changed) {
                $updates['closing_note'] = $revision->new_closing_note;
            }

            if ($updates !== []) {
                $case->update($updates);
            }

            if ($revision->note_changed && $revision->note_id) {
                StudentCaseNote::query()
                    ->whereKey($revision->note_id)
                    ->where('student_case_id', $case->id)
                    ->update(['body' => $revision->new_note]);
            }

            $revision->update([
                'status' => StudentCaseRevision::STATUS_ACCEPTED,
                'reviewed_by_user_id' => $reviewer->id,
                'reviewed_at' => now(),
            ]);

            $this->audit->log(
                action: 'Case Edit Approved',
                auditable: $case,
                newValues: ['revision_id' => $revision->id],
                user: $reviewer,
            );

            return $case->fresh(['closedBy', 'currentAssignee', 'openedBy']);
        });
    }

    public function rejectRevision(StudentCaseRevision $revision, User $reviewer): StudentCaseRevision
    {
        $revision->loadMissing('studentCase');
        $case = $revision->studentCase;

        if (! $case || ! $revision->isPending() || ! $this->canReviewRevision($case, $reviewer)) {
            throw ValidationException::withMessages([
                'case' => 'You are not allowed to reject this edit.',
            ]);
        }

        $revision->update([
            'status' => StudentCaseRevision::STATUS_REJECTED,
            'reviewed_by_user_id' => $reviewer->id,
            'reviewed_at' => now(),
        ]);

        $this->audit->log(
            action: 'Case Edit Rejected',
            auditable: $case,
            newValues: ['revision_id' => $revision->id],
            user: $reviewer,
        );

        return $revision->fresh('reviewer');
    }

    /**
     * @return Collection<int, StudentCase>
     */
    public function forStudent(Student $student, ?User $viewer = null): Collection
    {
        $viewer ??= auth()->user();

        $query = StudentCase::query()
            ->where('student_id', $student->id)
            ->with([
                'currentAssignee',
                'openedBy',
                'closedBy',
                'assignments.toUser',
                'assignments.fromUser',
                'assignments.assignedBy',
                'calls.staff',
                'notes.author',
                'revisions.author',
                'revisions.reviewer',
            ])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at');

        if ($viewer && ! CrmAccess::can($viewer, CrmPermission::CasesViewAll)) {
            $query->where(function ($inner) use ($viewer): void {
                $inner->where('current_assignee_user_id', $viewer->id)
                    ->orWhere('closed_by_user_id', $viewer->id);
            });
        }

        return $query->get();
    }

    public function openCountForStudent(Student $student): int
    {
        return StudentCase::query()
            ->where('student_id', $student->id)
            ->where('status', StudentCaseStatus::Open)
            ->count();
    }

    /**
     * @return array<int, array{
     *     id: int,
     *     case_number: string,
     *     title: string,
     *     type_label: string,
     *     status_label: string,
     *     assignee_name: string,
     *     opened_at_label: string,
     *     is_open: bool,
     * }>
     */
    public function overviewBanners(Student $student, ?User $viewer = null): array
    {
        $viewer ??= auth()->user();

        $query = StudentCase::query()
            ->where('student_id', $student->id)
            ->where('status', StudentCaseStatus::Open)
            ->with('currentAssignee')
            ->orderByDesc('opened_at');

        if ($viewer && ! CrmAccess::can($viewer, CrmPermission::CasesViewAll)) {
            $query->where(function ($inner) use ($viewer): void {
                $inner->where('current_assignee_user_id', $viewer->id)
                    ->orWhere('closed_by_user_id', $viewer->id);
            });
        }

        return $query
            ->get()
            ->map(fn (StudentCase $case): array => [
                'id' => $case->id,
                'case_number' => $case->case_number,
                'title' => $case->title,
                'type_label' => $case->case_type->label(),
                'status_label' => $case->status->label(),
                'assignee_name' => $case->currentAssignee?->name ?? 'Unassigned',
                'opened_at_label' => $case->opened_at?->format('d M Y'),
                'is_open' => true,
            ])
            ->all();
    }

    public function openCountForAssignee(User $staff): int
    {
        return StudentCase::query()
            ->where('current_assignee_user_id', $staff->id)
            ->where('status', StudentCaseStatus::Open)
            ->count();
    }

    /**
     * @return array{open: int, closed: int, total: int}
     */
    public function statsForAssignee(User $staff): array
    {
        $base = StudentCase::query()->where('current_assignee_user_id', $staff->id);
        $open = (clone $base)->where('status', StudentCaseStatus::Open)->count();
        $closed = (clone $base)->where('status', StudentCaseStatus::Closed)->count();

        return [
            'open' => $open,
            'closed' => $closed,
            'total' => $open + $closed,
        ];
    }

    /**
     * @return array{open: int, closed: int, total: int}
     */
    public function statsAll(): array
    {
        $open = StudentCase::query()->where('status', StudentCaseStatus::Open)->count();
        $closed = StudentCase::query()->where('status', StudentCaseStatus::Closed)->count();

        return [
            'open' => $open,
            'closed' => $closed,
            'total' => $open + $closed,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, StudentCase>
     */
    public function paginateForAssignee(
        User $staff,
        string $statusFilter = 'open',
        ?string $search = null,
        ?string $caseType = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = StudentCase::query()
            ->where('current_assignee_user_id', $staff->id)
            ->with([
                'student.activeEnrollment.course',
                'openedBy',
                'currentAssignee',
                'assignments' => fn ($assignmentQuery) => $assignmentQuery->latest()->limit(1),
            ]);

        $this->applyCaseListFilters($query, $statusFilter, $search, $caseType);

        return $query->paginate(
            $perPage ?? \App\Support\CrmPagination::PER_PAGE,
            ['*'],
            'page',
            $page,
        );
    }

    /**
     * @return LengthAwarePaginator<int, StudentCase>
     */
    public function paginateAll(
        string $statusFilter = 'open',
        ?string $search = null,
        ?int $assigneeUserId = null,
        ?string $caseType = null,
        ?int $perPage = null,
        ?int $page = null,
    ): LengthAwarePaginator {
        $query = StudentCase::query()
            ->with([
                'student.activeEnrollment.course',
                'openedBy',
                'currentAssignee',
                'assignments' => fn ($assignmentQuery) => $assignmentQuery->latest()->limit(1),
            ]);

        if ($assigneeUserId) {
            $query->where('current_assignee_user_id', $assigneeUserId);
        }

        $this->applyCaseListFilters($query, $statusFilter, $search, $caseType);

        return $query->paginate(
            $perPage ?? \App\Support\CrmPagination::PER_PAGE,
            ['*'],
            'page',
            $page,
        );
    }

    public function canView(StudentCase $case, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if (CrmAccess::can($viewer, CrmPermission::CasesViewAll)) {
            return true;
        }

        if (! CrmAccess::can($viewer, CrmPermission::CasesView)) {
            return false;
        }

        return in_array($viewer->id, array_filter([
            $case->current_assignee_user_id,
            $case->closed_by_user_id,
        ]), true);
    }

    public function canTransfer(StudentCase $case, ?User $viewer): bool
    {
        if (! $case->isOpen() || ! $viewer) {
            return false;
        }

        if ($this->canReassignAsAdmin($case, $viewer)) {
            return true;
        }

        return $this->isCurrentAssignee($case, $viewer)
            && CrmAccess::can($viewer, CrmPermission::CasesAssign);
    }

    public function canReassignAsAdmin(StudentCase $case, ?User $viewer): bool
    {
        return $case->isOpen() && $this->isSuperAdmin($viewer);
    }

    public function canOpenAsAdmin(?User $viewer, Student $student): bool
    {
        return CrmAccess::can($viewer, CrmPermission::CasesOpen)
            && $student->activeEnrollment()->exists();
    }

    public function isSuperAdmin(?User $viewer): bool
    {
        return $viewer?->hasRole(RoleName::SuperAdmin->value) ?? false;
    }

    public function canClose(StudentCase $case, ?User $viewer): bool
    {
        if (! $case->isOpen() || ! $viewer) {
            return false;
        }

        if ($this->isSuperAdmin($viewer)) {
            return true;
        }

        if (! CrmAccess::can($viewer, CrmPermission::CasesClose)) {
            return false;
        }

        if ($this->isCurrentAssignee($case, $viewer)) {
            return true;
        }

        return $this->latestReopenNote($case)?->user_id === $viewer->id;
    }

    public function canLogCall(StudentCase $case, ?User $viewer): bool
    {
        return $this->isCurrentAssignee($case, $viewer);
    }

    public function canAddUpdate(StudentCase $case, ?User $viewer, bool $fromMeeting = false): bool
    {
        if (! $case->isOpen() || ! $viewer) {
            return false;
        }

        if ($this->isSuperAdmin($viewer) || $this->isCurrentAssignee($case, $viewer)) {
            return true;
        }

        if (! $fromMeeting) {
            return false;
        }

        return VisitMeetingAssignment::query()
            ->where('student_id', $case->student_id)
            ->where('assigned_to_user_id', $viewer->id)
            ->where('status', VisitMeetingAssignmentStatus::Closed)
            ->where('closed_at', '>=', now()->subMinutes(5))
            ->exists();
    }

    public function canEditDetails(StudentCase $case, ?User $viewer): bool
    {
        if (! $viewer) {
            return false;
        }

        if ($this->isSuperAdmin($viewer)) {
            return true;
        }

        if ($viewer->id === $case->current_assignee_user_id) {
            return true;
        }

        return ! $case->isOpen() && $viewer->id === $case->closed_by_user_id;
    }

    public function canEditNote(StudentCase $case, StudentCaseNote $note, ?User $viewer): bool
    {
        if (! $viewer || ! $note->isMeetingUpdate() || $note->student_case_id !== $case->id) {
            return false;
        }

        if ($this->isSuperAdmin($viewer)) {
            return true;
        }

        return $viewer->id === $note->user_id
            || $viewer->id === $case->current_assignee_user_id;
    }

    public function canRequestEdit(StudentCase $case, ?User $viewer): bool
    {
        if (! $case->isOpen() || ! $viewer || $this->isSuperAdmin($viewer)) {
            return false;
        }

        return $viewer->id === $case->current_assignee_user_id;
    }

    public function canReviewRevision(StudentCase $case, ?User $viewer): bool
    {
        return $this->isSuperAdmin($viewer);
    }

    public function canReopen(StudentCase $case, ?User $viewer): bool
    {
        if ($case->isOpen() || ! $viewer) {
            return false;
        }

        if ($this->isSuperAdmin($viewer)) {
            return true;
        }

        return $viewer->id === $case->current_assignee_user_id
            || $viewer->id === $case->closed_by_user_id;
    }

    public function isCurrentAssignee(StudentCase $case, ?User $viewer): bool
    {
        return $viewer
            && $case->isOpen()
            && $case->current_assignee_user_id === $viewer->id;
    }

    /**
     * @return SupportCollection<int, array{
     *     type: string,
     *     label: string,
     *     occurred_at: Carbon,
     *     summary: ?string,
     *     detail: ?string,
     *     actor_name: ?string,
     *     status_label: ?string,
     *     note_id: ?int,
     * }>
     */
    public function activityTrail(StudentCase $case): SupportCollection
    {
        $case->loadMissing([
            'assignments.fromUser',
            'assignments.toUser',
            'assignments.assignedBy',
            'calls.staff',
            'notes.author',
            'revisions.author',
            'revisions.reviewer',
            'closedBy',
        ]);

        $items = collect();

        foreach ($case->assignments as $assignment) {
            $items->push([
                'type' => 'assignment',
                'label' => $assignment->fromUser
                    ? 'Transferred'
                    : 'Case opened',
                'occurred_at' => $assignment->created_at ?? now(),
                'summary' => $this->trailNote($assignment->note, $case->summary, $case->title),
                'detail' => $assignment->fromUser
                    ? $assignment->fromUser->name.' → '.$assignment->toUser->name
                    : 'Assigned to '.$assignment->toUser->name,
                'actor_name' => $assignment->assignedBy?->name,
                'status_label' => null,
                'note_id' => null,
            ]);
        }

        foreach ($case->notes as $note) {
            $items->push([
                'type' => 'note',
                'label' => $note->kind === StudentCaseNote::KIND_REOPEN
                    ? 'Case reopened'
                    : 'Meeting update',
                'occurred_at' => $note->created_at ?? now(),
                'summary' => $note->kind === StudentCaseNote::KIND_REOPEN
                    ? 'Earlier closing note: '.$note->body
                    : $note->body,
                'detail' => $note->updated_at && $note->created_at && $note->updated_at->gt($note->created_at)
                    ? 'Edited '.$note->updated_at->format('d M Y, h:i A')
                    : null,
                'actor_name' => $note->author?->name,
                'status_label' => null,
                'note_id' => $note->id,
            ]);
        }

        foreach ($case->revisions as $revision) {
            $items->push([
                'type' => 'revision',
                'label' => match ($revision->status) {
                    StudentCaseRevision::STATUS_PENDING => 'Edit requested',
                    StudentCaseRevision::STATUS_ACCEPTED => 'Edit approved',
                    StudentCaseRevision::STATUS_REJECTED => 'Edit rejected',
                    default => 'Remark updated',
                },
                'occurred_at' => $revision->created_at ?? now(),
                'summary' => null,
                'detail' => $revision->reviewer
                    ? 'Checked by '.$revision->reviewer->name.($revision->reviewed_at ? ' · '.$revision->reviewed_at->format('d M Y, h:i A') : '')
                    : null,
                'actor_name' => $revision->author?->name,
                'status_label' => match ($revision->status) {
                    StudentCaseRevision::STATUS_PENDING => 'Waiting',
                    StudentCaseRevision::STATUS_ACCEPTED => 'Approved',
                    StudentCaseRevision::STATUS_REJECTED => 'Rejected',
                    default => null,
                },
                'note_id' => null,
                'revision_id' => $revision->id,
                'revision_status' => $revision->status,
                'changes' => $this->revisionChanges($revision),
            ]);
        }

        foreach ($case->calls as $call) {
            $items->push([
                'type' => 'call',
                'label' => $call->call_direction->label().' call',
                'occurred_at' => $call->called_at ?? now(),
                'summary' => filled($call->call_notes) ? $call->call_notes : $call->call_status->label(),
                'detail' => $call->who_answered?->label(),
                'actor_name' => $call->staff?->name,
                'status_label' => $call->call_status->label(),
                'note_id' => null,
            ]);
        }

        if ($case->closed_at) {
            $items->push([
                'type' => 'closed',
                'label' => 'Case closed',
                'occurred_at' => $case->closed_at,
                'summary' => $this->trailNote($case->closing_note, $case->summary, $case->title),
                'detail' => null,
                'actor_name' => $case->closedBy?->name,
                'status_label' => $case->status->label(),
                'note_id' => null,
            ]);
        }

        return $items
            ->sortBy(fn (array $item): int => $item['occurred_at']->timestamp)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public static function activeStaffOptions(): array
    {
        return \App\Support\StaffOptions::assignableStaffOptions();
    }

    protected function latestReopenNote(StudentCase $case): ?StudentCaseNote
    {
        $case->loadMissing('notes');

        return $case->notes
            ->where('kind', StudentCaseNote::KIND_REOPEN)
            ->sortByDesc('id')
            ->first();
    }

    protected function pendingRevision(StudentCase $case): ?StudentCaseRevision
    {
        return $case->revisions()
            ->where('status', StudentCaseRevision::STATUS_PENDING)
            ->latest('id')
            ->first();
    }

    /**
     * @param  array{title: ?string, summary: ?string, closing_note: ?string}  $previous
     * @return array<string, mixed>
     */
    protected function revisionPayload(array $previous, string $title, ?string $summary, ?string $closingNote): array
    {
        $payload = [];

        if ($this->textChanged($previous['title'] ?? null, $title)) {
            $payload['title_changed'] = true;
            $payload['old_title'] = $previous['title'];
            $payload['new_title'] = $title;
        }

        if ($this->textChanged($previous['summary'] ?? null, $summary)) {
            $payload['summary_changed'] = true;
            $payload['old_summary'] = $previous['summary'];
            $payload['new_summary'] = $summary;
        }

        if ($this->textChanged($previous['closing_note'] ?? null, $closingNote)) {
            $payload['closing_note_changed'] = true;
            $payload['old_closing_note'] = $previous['closing_note'];
            $payload['new_closing_note'] = $closingNote;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    protected function storeRevision(StudentCase $case, User $user, string $status, array $payload): ?StudentCaseRevision
    {
        $changed = ($payload['title_changed'] ?? false)
            || ($payload['summary_changed'] ?? false)
            || ($payload['closing_note_changed'] ?? false)
            || ($payload['note_changed'] ?? false);

        if (! $changed) {
            return null;
        }

        return StudentCaseRevision::query()->create([
            'student_case_id' => $case->id,
            'user_id' => $user->id,
            'status' => $status,
            ...$payload,
        ]);
    }

    /**
     * @return list<array{label: string, old: ?string, new: ?string}>
     */
    protected function revisionChanges(StudentCaseRevision $revision): array
    {
        $changes = [];

        if ($revision->title_changed) {
            $changes[] = ['label' => 'Title', 'old' => $revision->old_title, 'new' => $revision->new_title];
        }

        if ($revision->summary_changed) {
            $changes[] = ['label' => 'What happened', 'old' => $revision->old_summary, 'new' => $revision->new_summary];
        }

        if ($revision->closing_note_changed) {
            $changes[] = ['label' => 'Closing note', 'old' => $revision->old_closing_note, 'new' => $revision->new_closing_note];
        }

        if ($revision->note_changed) {
            $changes[] = ['label' => 'Meeting note', 'old' => $revision->old_note, 'new' => $revision->new_note];
        }

        return $changes;
    }

    protected function textChanged(?string $left, ?string $right): bool
    {
        return strcasecmp(trim((string) $left), trim((string) $right)) !== 0;
    }

    protected function trailNote(?string $note, ?string $summary, ?string $title): ?string
    {
        $note = trim((string) $note);

        if ($note === '' || strcasecmp($note, 'Case opened.') === 0) {
            return null;
        }

        if ($this->sameText($note, $summary) || $this->sameText($note, $title)) {
            return null;
        }

        return $note;
    }

    protected function sameText(?string $left, ?string $right): bool
    {
        $left = preg_replace('/\s+/', ' ', trim((string) $left)) ?? '';
        $right = preg_replace('/\s+/', ' ', trim((string) $right)) ?? '';

        return $left !== '' && strcasecmp($left, $right) === 0;
    }

    protected function notifyAssignee(StudentCase $case, User $assignee, User $assignedBy): void
    {
        if ($assignee->id === $assignedBy->id) {
            return;
        }

        $studentName = $case->student?->name ?? 'Student';
        $profileUrl = $case->student_id
            ? StudentProfilePage::getUrl(['record' => $case->student_id, 'tab' => 'cases'])
            : null;

        $notification = Notification::make()
            ->title('Case assigned to you')
            ->body("{$assignedBy->name} assigned {$case->case_number} ({$studentName}) to you.");

        $actions = [];

        if (MyMeetingsPage::canAccess()) {
            $actions[] = \Filament\Actions\Action::make('my_cases')
                ->label('My work')
                ->url(MyMeetingsPage::getUrl(['tab' => 'my_cases']));
        }

        if ($profileUrl) {
            $actions[] = \Filament\Actions\Action::make('view')
                ->label('Open profile')
                ->url($profileUrl);
        }

        if ($actions !== []) {
            $notification->actions($actions);
        }

        $notification->sendToDatabase($assignee);

        try {
            app(WebPushService::class)->notifyCaseAssigned(
                $assignee,
                $case,
                $studentName,
                $profileUrl,
            );
        } catch (\Throwable $exception) {
            Log::warning('Case assign web push failed: '.$exception->getMessage());
        }
    }

    protected function applyCaseListFilters(
        \Illuminate\Database\Eloquent\Builder $query,
        string $statusFilter,
        ?string $search,
        ?string $caseType,
    ): void {
        if ($statusFilter === 'closed') {
            $query->where('status', StudentCaseStatus::Closed)->orderByDesc('closed_at');
        } elseif ($statusFilter === 'all') {
            $query->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")->orderByDesc('opened_at');
        } else {
            $query->where('status', StudentCaseStatus::Open)->orderByDesc('opened_at');
        }

        if (filled($caseType) && CampusVisitPurpose::tryFrom($caseType)) {
            $query->where('case_type', $caseType);
        }

        if (filled($search)) {
            $term = trim($search);

            $query->where(function ($inner) use ($term): void {
                $inner->where('case_number', 'like', '%'.strtoupper($term).'%')
                    ->orWhere('title', 'like', '%'.$term.'%')
                    ->orWhereHas('student', function ($studentQuery) use ($term): void {
                        $studentQuery->where('name', 'like', '%'.$term.'%')
                            ->orWhere('mobile', 'like', '%'.preg_replace('/\D/', '', $term).'%');
                    });
            });
        }
    }

    protected function flushNavBadges(?int ...$staffUserIds): void
    {
        CrmNavBadges::flushCaseBadgeCache(...$staffUserIds);
    }
}

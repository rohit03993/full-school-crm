<?php

namespace App\Services;

use App\Enums\CampusVisitPurpose;
use App\Enums\CrmPermission;
use App\Enums\NumberSequenceType;
use App\Enums\StudentCaseStatus;
use App\Filament\Pages\MyCasesPage;
use App\Filament\Pages\StudentProfilePage;
use App\Models\Student;
use App\Models\StudentCase;
use App\Models\StudentCaseAssignment;
use App\Models\User;
use App\Models\Visit;
use App\Support\CrmAccess;
use App\Support\CrmNavBadges;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
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

        if ($assignee->id !== $openedBy->id && blank($handoffNote)) {
            throw ValidationException::withMessages([
                'handoff_note' => 'Add a handoff note when assigning the case to another staff member.',
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

    /**
     * @return Collection<int, StudentCase>
     */
    public function forStudent(Student $student): Collection
    {
        return StudentCase::query()
            ->where('student_id', $student->id)
            ->with([
                'currentAssignee',
                'openedBy',
                'closedBy',
                'assignments.toUser',
                'assignments.fromUser',
                'assignments.assignedBy',
                'calls.staff',
            ])
            ->orderByRaw("CASE WHEN status = 'open' THEN 0 ELSE 1 END")
            ->orderByDesc('opened_at')
            ->get();
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
    public function overviewBanners(Student $student): array
    {
        return StudentCase::query()
            ->where('student_id', $student->id)
            ->where('status', StudentCaseStatus::Open)
            ->with('currentAssignee')
            ->orderByDesc('opened_at')
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
            $case->opened_by_user_id,
            $case->current_assignee_user_id,
            $case->closed_by_user_id,
        ]), true) || CrmAccess::can($viewer, CrmPermission::StudentsView);
    }

    public function canTransfer(StudentCase $case, ?User $viewer): bool
    {
        return $this->isCurrentAssignee($case, $viewer)
            && CrmAccess::can($viewer, CrmPermission::CasesAssign);
    }

    public function canClose(StudentCase $case, ?User $viewer): bool
    {
        return $this->isCurrentAssignee($case, $viewer)
            && CrmAccess::can($viewer, CrmPermission::CasesClose);
    }

    public function canLogCall(StudentCase $case, ?User $viewer): bool
    {
        return $this->isCurrentAssignee($case, $viewer);
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
     * }>
     */
    public function activityTrail(StudentCase $case): SupportCollection
    {
        $items = collect();

        foreach ($case->assignments as $assignment) {
            $items->push([
                'type' => 'assignment',
                'label' => $assignment->fromUser
                    ? 'Transferred'
                    : 'Case opened',
                'occurred_at' => $assignment->created_at ?? now(),
                'summary' => $assignment->note,
                'detail' => $assignment->fromUser
                    ? $assignment->fromUser->name.' → '.$assignment->toUser->name
                    : 'Assigned to '.$assignment->toUser->name,
                'actor_name' => $assignment->assignedBy?->name,
                'status_label' => null,
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
            ]);
        }

        if ($case->closed_at) {
            $items->push([
                'type' => 'closed',
                'label' => 'Case closed',
                'occurred_at' => $case->closed_at,
                'summary' => $case->closing_note,
                'detail' => null,
                'actor_name' => $case->closedBy?->name,
                'status_label' => $case->status->label(),
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

        if (MyCasesPage::canAccess()) {
            $actions[] = \Filament\Actions\Action::make('my_cases')
                ->label('My cases')
                ->url(MyCasesPage::getUrl());
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

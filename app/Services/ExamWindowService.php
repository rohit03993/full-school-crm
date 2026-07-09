<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Enums\CrmPermission;
use App\Enums\ExamWindowStatus;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\CourseSubject;
use App\Models\ExamWindow;
use App\Models\ExamWindowSubject;
use App\Models\User;
use App\Support\CrmAccess;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ExamWindowService
{
    public function __construct(
        protected ActivityMarksBulkImportService $marksImport,
        protected AuditService $audit,
    ) {}

    /**
     * @param  array{batch_id: int, activity_type_id: int, test_name: string, session_date: string, open_immediately?: bool, remarks?: ?string}  $data
     */
    public function create(array $data, User $admin): ExamWindow
    {
        $batch = Batch::query()->with(['course.subjects' => fn ($q) => $q->active()->ordered()])->findOrFail((int) $data['batch_id']);
        $activityType = ActivityType::query()->findOrFail((int) $data['activity_type_id']);

        if (! $activityType->supportsScoring()) {
            throw ValidationException::withMessages([
                'activity_type_id' => 'Select an exam type that supports marks.',
            ]);
        }

        $subjects = $batch->course?->subjects?->filter(fn (CourseSubject $s): bool => $s->is_active) ?? collect();

        if ($subjects->isEmpty()) {
            throw ValidationException::withMessages([
                'batch_id' => 'Add subjects on the programme first — then create an exam window.',
            ]);
        }

        $testName = trim((string) $data['test_name']);
        $sessionDate = (string) $data['session_date'];
        $testKey = $this->marksImport->buildTestKey($testName, $sessionDate);

        if (ExamWindow::query()->where('batch_id', $batch->id)->where('test_key', $testKey)->exists()) {
            throw ValidationException::withMessages([
                'test_name' => 'An exam with this name and date already exists for this section.',
            ]);
        }

        $openImmediately = (bool) ($data['open_immediately'] ?? true);

        return DB::transaction(function () use ($batch, $activityType, $testName, $sessionDate, $testKey, $subjects, $admin, $openImmediately, $data): ExamWindow {
            $window = ExamWindow::query()->create([
                'batch_id' => $batch->id,
                'activity_type_id' => $activityType->id,
                'test_name' => $testName,
                'session_date' => $sessionDate,
                'test_key' => $testKey,
                'status' => $openImmediately ? ExamWindowStatus::Open : ExamWindowStatus::Draft,
                'created_by_user_id' => $admin->id,
                'remarks' => filled($data['remarks'] ?? null) ? trim((string) $data['remarks']) : null,
            ]);

            foreach ($subjects as $subject) {
                $maxMarks = max(1, (int) ($subject->default_max_marks ?? 100));

                $windowSubject = ExamWindowSubject::query()->create([
                    'exam_window_id' => $window->id,
                    'course_subject_id' => $subject->id,
                    'max_marks' => $maxMarks,
                ]);

                $session = $this->provisionSession($window, $windowSubject, $subject, $activityType, $admin);
                $windowSubject->update(['activity_session_id' => $session->id]);
            }

            $this->audit->log(
                'exam_window_created',
                $window,
                null,
                [
                    'test_key' => $testKey,
                    'subject_count' => $subjects->count(),
                    'status' => $window->status->value,
                ],
                user: $admin,
            );

            return $window->fresh([
                'batch.course',
                'batch.academicSession',
                'activityType',
                'subjects.courseSubject',
                'subjects.activitySession',
            ]);
        });
    }

    public function open(ExamWindow $window, User $admin): ExamWindow
    {
        $this->assertAdmin($admin);

        if ($window->status !== ExamWindowStatus::Draft) {
            throw ValidationException::withMessages([
                'status' => 'Only draft exams can be opened for teachers.',
            ]);
        }

        $window->update(['status' => ExamWindowStatus::Open]);

        $this->audit->log('exam_window_opened', $window, null, [], user: $admin);

        return $window->fresh();
    }

    public function submit(ExamWindow $window, User $user): ExamWindow
    {
        if (! $this->canUserSubmit($user, $window)) {
            throw ValidationException::withMessages([
                'status' => 'You are not allowed to submit this exam for approval.',
            ]);
        }

        if ($window->status !== ExamWindowStatus::Open) {
            throw ValidationException::withMessages([
                'status' => 'Only open exams can be submitted for approval.',
            ]);
        }

        $window->update([
            'status' => ExamWindowStatus::Submitted,
            'submitted_by_user_id' => $user->id,
            'submitted_at' => now(),
        ]);

        $this->audit->log('exam_window_submitted', $window, null, [], user: $user);

        return $window->fresh();
    }

    public function approve(ExamWindow $window, User $admin): ExamWindow
    {
        $this->assertAdmin($admin);

        if ($window->status !== ExamWindowStatus::Submitted) {
            throw ValidationException::withMessages([
                'status' => 'Only submitted exams can be approved.',
            ]);
        }

        $window->update([
            'status' => ExamWindowStatus::Approved,
            'approved_by_user_id' => $admin->id,
            'approved_at' => now(),
        ]);

        $this->audit->log('exam_window_approved', $window, null, [], user: $admin);

        return $window->fresh();
    }

    public function findForGroupKey(string $groupKey): ?ExamWindow
    {
        return ExamWindow::query()
            ->where('test_key', $groupKey)
            ->with(['batch', 'subjects.courseSubject'])
            ->first();
    }

    public function assertApprovedForPublish(string $groupKey): void
    {
        $window = $this->findForGroupKey($groupKey);

        if (! $window) {
            return;
        }

        if ($window->status !== ExamWindowStatus::Approved) {
            throw ValidationException::withMessages([
                'status' => 'Approve the exam window before publishing results. Open Exam windows from Academics.',
            ]);
        }
    }

    public function recordMarksEntry(ActivitySession $session, User $staff): void
    {
        $subjectId = (int) ($session->metadataValue('course_subject_id') ?? 0);

        if ($subjectId <= 0) {
            return;
        }

        $windowSubject = ExamWindowSubject::query()
            ->where('activity_session_id', $session->id)
            ->orWhere(function ($query) use ($subjectId, $session): void {
                $query->where('course_subject_id', $subjectId)
                    ->whereHas('examWindow', fn ($w) => $w
                        ->where('batch_id', $session->batch_id)
                        ->where('test_key', $session->metadataValue('test_key')));
            })
            ->first();

        if (! $windowSubject) {
            return;
        }

        $windowSubject->update([
            'activity_session_id' => $session->id,
            'entered_by_user_id' => $staff->id,
            'marks_entered_at' => now(),
        ]);
    }

    /**
     * @return array{
     *     total: int,
     *     entered: int,
     *     pending: int,
     *     subjects: list<array{id: int, name: string, max_marks: int, entered: bool, entered_by: ?string}>
     * }
     */
    public function progress(ExamWindow $window): array
    {
        $window->loadMissing(['subjects.courseSubject', 'subjects.enteredBy']);

        $subjects = $window->subjects->map(fn (ExamWindowSubject $row): array => [
            'id' => $row->id,
            'name' => $row->courseSubject?->name ?? 'Subject',
            'max_marks' => $row->max_marks,
            'entered' => $row->hasMarksEntered(),
            'entered_by' => $row->enteredBy?->name,
            'activity_session_id' => $row->activity_session_id,
        ])->values()->all();

        $entered = collect($subjects)->where('entered', true)->count();

        return [
            'total' => count($subjects),
            'entered' => $entered,
            'pending' => count($subjects) - $entered,
            'subjects' => $subjects,
        ];
    }

    /**
     * @return list<array{
     *     window: ExamWindow,
     *     subject: ExamWindowSubject,
     *     role: BatchStaffRole,
     * }>
     */
    public function pendingEntriesForUser(User $user): array
    {
        $assignments = BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->with(['batch', 'courseSubject'])
            ->get();

        if ($assignments->isEmpty()) {
            return [];
        }

        $batchIds = $assignments->pluck('batch_id')->unique()->all();

        $windows = ExamWindow::query()
            ->whereIn('batch_id', $batchIds)
            ->where('status', ExamWindowStatus::Open)
            ->with(['batch.course', 'batch.academicSession', 'subjects.courseSubject', 'activityType'])
            ->orderByDesc('session_date')
            ->get();

        $rows = [];

        foreach ($windows as $window) {
            foreach ($window->subjects as $windowSubject) {
                if ($windowSubject->hasMarksEntered()) {
                    continue;
                }

                if ($this->canUserEnterSubject($user, $window, $windowSubject)) {
                    $rows[] = [
                        'window' => $window,
                        'subject' => $windowSubject,
                        'role' => $this->roleForSubject($user, $window, $windowSubject),
                    ];
                }
            }
        }

        return $rows;
    }

    /**
     * @return list<array{window: ExamWindow, can_submit: bool}>
     */
    public function submitCandidatesForUser(User $user): array
    {
        $leadBatchIds = BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->where('role', BatchStaffRole::LeadTeacher)
            ->pluck('batch_id')
            ->all();

        if ($leadBatchIds === [] && ! $this->isAdmin($user)) {
            return [];
        }

        $query = ExamWindow::query()
            ->where('status', ExamWindowStatus::Open)
            ->with(['batch.course', 'batch.academicSession', 'activityType'])
            ->orderByDesc('session_date');

        if (! $this->isAdmin($user)) {
            $query->whereIn('batch_id', $leadBatchIds);
        }

        return $query->get()
            ->map(fn (ExamWindow $window): array => [
                'window' => $window,
                'can_submit' => $this->canUserSubmit($user, $window),
            ])
            ->all();
    }

    public function canUserEnterSubject(User $user, ExamWindow $window, ExamWindowSubject $windowSubject): bool
    {
        if (! $window->status->allowsTeacherEntry()) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        if (CrmAccess::can($user, CrmPermission::MarksImport)) {
            return true;
        }

        $subjectId = $windowSubject->course_subject_id;

        return BatchStaffAssignment::query()
            ->where('batch_id', $window->batch_id)
            ->where('user_id', $user->id)
            ->where(function ($query) use ($subjectId): void {
                $query->where(fn ($q) => $q
                    ->where('role', BatchStaffRole::SubjectTeacher)
                    ->where('course_subject_id', $subjectId))
                    ->orWhere('role', BatchStaffRole::LeadTeacher);
            })
            ->exists();
    }

    public function canUserSubmit(User $user, ExamWindow $window): bool
    {
        if ($window->status !== ExamWindowStatus::Open) {
            return false;
        }

        if ($this->isAdmin($user)) {
            return true;
        }

        return BatchStaffAssignment::query()
            ->where('batch_id', $window->batch_id)
            ->where('user_id', $user->id)
            ->where('role', BatchStaffRole::LeadTeacher)
            ->exists();
    }

    public function canUserApprove(User $user): bool
    {
        return $this->isAdmin($user);
    }

    protected function roleForSubject(User $user, ExamWindow $window, ExamWindowSubject $windowSubject): BatchStaffRole
    {
        $isSubjectTeacher = BatchStaffAssignment::query()
            ->where('batch_id', $window->batch_id)
            ->where('user_id', $user->id)
            ->where('role', BatchStaffRole::SubjectTeacher)
            ->where('course_subject_id', $windowSubject->course_subject_id)
            ->exists();

        return $isSubjectTeacher ? BatchStaffRole::SubjectTeacher : BatchStaffRole::LeadTeacher;
    }

    protected function isAdmin(User $user): bool
    {
        return CrmAccess::canAny(
            $user,
            CrmPermission::AcademicsManage,
            CrmPermission::MarksImport,
        );
    }

    protected function assertAdmin(User $user): void
    {
        if (! $this->isAdmin($user)) {
            throw ValidationException::withMessages([
                'permission' => 'You do not have permission to manage exam windows.',
            ]);
        }
    }

    protected function provisionSession(
        ExamWindow $window,
        ExamWindowSubject $windowSubject,
        CourseSubject $subject,
        ActivityType $activityType,
        User $admin,
    ): ActivitySession {
        $existing = ActivitySession::query()
            ->where('activity_type_id', $activityType->id)
            ->where('batch_id', $window->batch_id)
            ->whereDate('session_date', $window->session_date)
            ->where('metadata->test_key', $window->test_key)
            ->where('metadata->subject', $subject->name)
            ->first();

        if ($existing) {
            $existing->update([
                'metadata' => array_merge($existing->metadata ?? [], [
                    'course_subject_id' => $subject->id,
                    'exam_window_id' => $window->id,
                ]),
            ]);

            return $existing;
        }

        return ActivitySession::query()->create([
            'activity_type_id' => $activityType->id,
            'title' => "{$window->test_name} — {$subject->name}",
            'session_date' => $window->session_date,
            'batch_id' => $window->batch_id,
            'metadata' => [
                'test_key' => $window->test_key,
                'test_name' => $window->test_name,
                'subject' => $subject->name,
                'max_marks' => $windowSubject->max_marks,
                'course_subject_id' => $subject->id,
                'exam_window_id' => $window->id,
            ],
            'created_by_user_id' => $admin->id,
        ]);
    }
}

<?php

namespace App\Services;

use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Enums\RoleName;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HomeworkCheckService
{
    public function __construct(
        protected HomeworkCheckWhatsAppService $whatsapp,
        protected BatchStaffAssignmentService $assignments,
    ) {}

    /**
     * @return array<int, string>
     */
    public function batchOptionsFor(User $user): array
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return Batch::query()
                ->with(['course', 'academicSession'])
                ->orderBy('name')
                ->get()
                ->mapWithKeys(fn (Batch $batch): array => [
                    $batch->id => $batch->displayLabel(),
                ])
                ->all();
        }

        $ids = BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->pluck('batch_id')
            ->unique()
            ->all();

        return Batch::query()
            ->whereIn('id', $ids)
            ->with(['course', 'academicSession'])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(fn (Batch $batch): array => [
                $batch->id => $batch->displayLabel(),
            ])
            ->all();
    }

    public function userCanAccessBatch(User $user, int $batchId): bool
    {
        if ($user->hasRole(RoleName::SuperAdmin->value)) {
            return Batch::query()->whereKey($batchId)->exists();
        }

        return BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->where('batch_id', $batchId)
            ->exists();
    }

    /**
     * @return array<int, string>
     */
    public function studentOptionsForBatch(int $batchId, ?string $search = null): array
    {
        $query = BatchStudent::query()
            ->where('batch_id', $batchId)
            ->where('is_active', true)
            ->with('student')
            ->whereHas('student', function ($q) use ($search): void {
                if (filled($search)) {
                    $q->where('name', 'like', '%'.trim($search).'%');
                }
            });

        return $query
            ->get()
            ->mapWithKeys(function (BatchStudent $row): array {
                $student = $row->student;
                if (! $student) {
                    return [];
                }

                $label = $student->name;
                if (filled($student->mobile)) {
                    $label .= ' · '.$student->mobile;
                }

                return [$student->id => $label];
            })
            ->all();
    }

    /**
     * @return array<int, string>
     */
    public function subjectOptionsForBatch(User $user, int $batchId): array
    {
        $batch = Batch::query()->with('course.subjects')->find($batchId);

        if (! $batch?->course_id) {
            return [];
        }

        $subjects = CourseSubject::query()
            ->where('course_id', $batch->course_id)
            ->where('is_active', true)
            ->ordered()
            ->get();

        if (! $user->hasRole(RoleName::SuperAdmin->value)) {
            $assignedSubjectIds = BatchStaffAssignment::query()
                ->where('user_id', $user->id)
                ->where('batch_id', $batchId)
                ->whereNotNull('course_subject_id')
                ->pluck('course_subject_id')
                ->all();

            $isLead = BatchStaffAssignment::query()
                ->where('user_id', $user->id)
                ->where('batch_id', $batchId)
                ->whereNull('course_subject_id')
                ->exists();

            if (! $isLead && $assignedSubjectIds !== []) {
                $subjects = $subjects->whereIn('id', $assignedSubjectIds);
            }
        }

        return $subjects
            ->mapWithKeys(fn (CourseSubject $subject): array => [
                $subject->id => $subject->displayLabel(),
            ])
            ->all();
    }

    public function normalizeCheckedOn(?string $checkedOn): string
    {
        $date = filled($checkedOn)
            ? Carbon::parse($checkedOn)->toDateString()
            : now()->toDateString();

        if ($date > now()->toDateString()) {
            throw ValidationException::withMessages([
                'check_date' => 'Homework check date cannot be in the future.',
            ]);
        }

        return $date;
    }

    /**
     * @return array{
     *     check: HomeworkCheck,
     *     whatsapp: array{queued: bool, message: string}
     * }
     */
    public function mark(
        User $teacher,
        int $batchId,
        int $studentId,
        int $courseSubjectId,
        string $topic,
        HomeworkCheckStatus $status,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
    ): array {
        if (! FeatureGate::enabled(LicenseFeature::Homework)) {
            throw ValidationException::withMessages([
                'status' => 'Homework feature is not enabled on your licence.',
            ]);
        }

        if (! $this->userCanAccessBatch($teacher, $batchId)) {
            throw ValidationException::withMessages([
                'batch_id' => 'You are not assigned to this class.',
            ]);
        }

        $topic = trim($topic);

        if ($topic === '') {
            $topic = "Today's homework";
        }

        $checkedOnDate = $this->normalizeCheckedOn($checkedOn);

        $batch = Batch::query()->with('course')->findOrFail($batchId);
        $student = Student::query()->findOrFail($studentId);
        $subject = CourseSubject::query()->findOrFail($courseSubjectId);
        $assignment = null;

        if ($homeworkAssignmentId) {
            $assignment = HomeworkAssignment::query()->findOrFail($homeworkAssignmentId);

            if ((int) $assignment->batch_id !== $batchId) {
                throw ValidationException::withMessages([
                    'homework_assignment_id' => 'Homework assignment does not belong to this class.',
                ]);
            }

            if ($topic === "Today's homework") {
                $topic = $assignment->title;
            }
        }

        if ((int) $subject->course_id !== (int) $batch->course_id) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Subject does not belong to this class programme.',
            ]);
        }

        $inBatch = BatchStudent::query()
            ->where('batch_id', $batchId)
            ->where('student_id', $studentId)
            ->where('is_active', true)
            ->exists();

        if (! $inBatch) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not in this class.',
            ]);
        }

        $check = HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'homework_assignment_id' => $assignment?->id,
            'subject_name' => $subject->displayLabel(),
            'topic' => $topic,
            'checked_on' => $checkedOnDate,
            'status' => $status,
            'parent_mobile' => filled($student->mobile) ? (string) $student->mobile : null,
            'notify_status' => $status === HomeworkCheckStatus::Done
                ? HomeworkCheckNotifyStatus::NotRequired
                : HomeworkCheckNotifyStatus::Pending,
            'created_by_user_id' => $teacher->id,
        ]);

        if ($status === HomeworkCheckStatus::Done) {
            return [
                'check' => $check,
                'whatsapp' => [
                    'queued' => false,
                    'message' => 'Saved as Done. No WhatsApp sent.',
                ],
            ];
        }

        $outcome = $this->whatsapp->notifyNotDone($check->fresh(['student', 'batch.course']), $teacher);

        $check->update([
            'notify_status' => $outcome['queued']
                ? HomeworkCheckNotifyStatus::Sent
                : HomeworkCheckNotifyStatus::Failed,
            'notified_at' => $outcome['queued'] ? now() : null,
        ]);

        return [
            'check' => $check->fresh(),
            'whatsapp' => $outcome,
        ];
    }

    /**
     * @param  list<int>  $studentIds
     * @return array{marked: int, whatsappQueued: int, whatsappFailed: int, errors: list<string>}
     */
    public function markMany(
        User $teacher,
        int $batchId,
        array $studentIds,
        int $courseSubjectId,
        string $topic,
        HomeworkCheckStatus $status,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
    ): array {
        $studentIds = collect($studentIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();

        $marked = 0;
        $whatsappQueued = 0;
        $whatsappFailed = 0;
        $errors = [];

        foreach ($studentIds as $studentId) {
            try {
                $result = $this->mark(
                    $teacher,
                    $batchId,
                    $studentId,
                    $courseSubjectId,
                    $topic,
                    $status,
                    $checkedOn,
                    $homeworkAssignmentId,
                );
                $marked++;

                if ($status === HomeworkCheckStatus::NotDone) {
                    if ($result['whatsapp']['queued']) {
                        $whatsappQueued++;
                    } else {
                        $whatsappFailed++;
                    }
                }
            } catch (ValidationException $exception) {
                $errors[] = (string) (collect($exception->errors())->flatten()->first() ?? 'Could not mark student '.$studentId);
            }
        }

        return compact('marked', 'whatsappQueued', 'whatsappFailed', 'errors');
    }

    /**
     * @return array{marked: int, whatsappQueued: int, whatsappFailed: int, errors: list<string>}
     */
    public function markRemainingDone(
        User $teacher,
        int $batchId,
        int $courseSubjectId,
        string $topic,
        ?string $checkedOn = null,
        ?string $search = null,
        ?int $homeworkAssignmentId = null,
    ): array {
        $ids = $this->rosterForBatch($batchId, $courseSubjectId, $search, $checkedOn)
            ->filter(fn (array $row): bool => blank($row['last_status']))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($ids === []) {
            return [
                'marked' => 0,
                'whatsappQueued' => 0,
                'whatsappFailed' => 0,
                'errors' => [],
            ];
        }

        return $this->markMany(
            $teacher,
            $batchId,
            $ids,
            $courseSubjectId,
            $topic,
            HomeworkCheckStatus::Done,
            $checkedOn,
            $homeworkAssignmentId,
        );
    }

    /**
     * @return array{queued: bool, message: string, check: HomeworkCheck}
     */
    public function resendWhatsApp(User $teacher, int $checkId): array
    {
        $check = HomeworkCheck::query()->with(['student', 'batch.course'])->findOrFail($checkId);

        if (! $this->userCanAccessBatch($teacher, (int) $check->batch_id)) {
            throw ValidationException::withMessages([
                'check_id' => 'You are not assigned to this class.',
            ]);
        }

        if ($check->status !== HomeworkCheckStatus::NotDone) {
            throw ValidationException::withMessages([
                'check_id' => 'WhatsApp can only be resent for Not Done marks.',
            ]);
        }

        if ($check->notify_status === HomeworkCheckNotifyStatus::Sent) {
            throw ValidationException::withMessages([
                'check_id' => 'WhatsApp was already sent for this mark.',
            ]);
        }

        $mobile = trim((string) ($check->student?->mobile ?: $check->parent_mobile));

        $check->update([
            'parent_mobile' => $mobile !== '' ? $mobile : null,
            'notify_status' => HomeworkCheckNotifyStatus::Pending,
            'notified_at' => null,
        ]);

        $outcome = $this->whatsapp->notifyNotDone($check->fresh(['student', 'batch.course']), $teacher);

        $check->update([
            'notify_status' => $outcome['queued']
                ? HomeworkCheckNotifyStatus::Sent
                : HomeworkCheckNotifyStatus::Failed,
            'notified_at' => $outcome['queued'] ? now() : null,
        ]);

        return [
            'queued' => $outcome['queued'],
            'message' => $outcome['message'],
            'check' => $check->fresh(),
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, mobile: ?string, check_id: ?int, last_status: ?string, last_notify: ?string, can_resend: bool, not_done_week: int}>
     */
    public function rosterForBatch(
        int $batchId,
        ?int $courseSubjectId = null,
        ?string $search = null,
        ?string $checkedOn = null,
    ): Collection {
        $checkedOnDate = $this->normalizeCheckedOn($checkedOn);

        $query = BatchStudent::query()
            ->where('batch_id', $batchId)
            ->where('is_active', true)
            ->with('student')
            ->whereHas('student', function ($q) use ($search): void {
                if (filled($search)) {
                    $q->where('name', 'like', '%'.trim($search).'%');
                }
            });

        $latestByStudent = collect();

        if ($courseSubjectId) {
            $latestByStudent = HomeworkCheck::query()
                ->where('batch_id', $batchId)
                ->where('course_subject_id', $courseSubjectId)
                ->whereDate('checked_on', $checkedOnDate)
                ->orderByDesc('id')
                ->get()
                ->unique('student_id')
                ->keyBy('student_id');
        }

        $rows = $query->get();
        $studentIds = $rows->pluck('student_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $notDoneWeek = $this->notDoneCountsThisWeek($studentIds);

        return $rows
            ->map(function (BatchStudent $row) use ($latestByStudent, $notDoneWeek): ?array {
                $student = $row->student;
                if (! $student) {
                    return null;
                }

                /** @var HomeworkCheck|null $latest */
                $latest = $latestByStudent->get($student->id);
                $canResend = $latest !== null
                    && $latest->status === HomeworkCheckStatus::NotDone
                    && in_array($latest->notify_status, [
                        HomeworkCheckNotifyStatus::Failed,
                        HomeworkCheckNotifyStatus::Pending,
                    ], true);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'mobile' => $student->mobile,
                    'check_id' => $latest?->id,
                    'last_status' => $latest?->status?->label(),
                    'last_notify' => $latest?->notify_status?->label(),
                    'can_resend' => $canResend,
                    'not_done_week' => (int) ($notDoneWeek[$student->id] ?? 0),
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return array<int, string>
     */
    public function assignmentOptionsForBatch(int $batchId): array
    {
        return HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->orderByDesc('published_at')
            ->limit(40)
            ->get()
            ->mapWithKeys(fn (HomeworkAssignment $assignment): array => [
                $assignment->id => $assignment->title
                    .($assignment->published_at ? ' · '.$assignment->published_at->format('d M') : ''),
            ])
            ->all();
    }

    public function notDoneCountThisWeek(int $studentId): int
    {
        [$from, $to] = $this->currentWeekDateBounds();

        return HomeworkCheck::query()
            ->where('student_id', $studentId)
            ->where('status', HomeworkCheckStatus::NotDone)
            ->whereDate('checked_on', '>=', $from)
            ->whereDate('checked_on', '<=', $to)
            ->count();
    }

    /**
     * @param  list<int>  $studentIds
     * @return array<int, int>
     */
    public function notDoneCountsThisWeek(array $studentIds): array
    {
        if ($studentIds === []) {
            return [];
        }

        [$from, $to] = $this->currentWeekDateBounds();

        return HomeworkCheck::query()
            ->whereIn('student_id', $studentIds)
            ->where('status', HomeworkCheckStatus::NotDone)
            ->whereDate('checked_on', '>=', $from)
            ->whereDate('checked_on', '<=', $to)
            ->selectRaw('student_id, COUNT(*) as aggregate')
            ->groupBy('student_id')
            ->pluck('aggregate', 'student_id')
            ->map(fn ($count): int => (int) $count)
            ->all();
    }

    /**
     * @return array{0: string, 1: string}
     */
    protected function currentWeekDateBounds(): array
    {
        return [
            now()->copy()->startOfWeek()->toDateString(),
            now()->copy()->endOfWeek()->toDateString(),
        ];
    }

    /**
     * @param  Collection<int, array{last_status: ?string}>  $roster
     * @return array{total: int, done: int, not_done: int, unmarked: int, done_pct: int}
     */
    public function daySummaryFromRoster(Collection $roster): array
    {
        $total = $roster->count();
        $done = $roster->where('last_status', HomeworkCheckStatus::Done->label())->count();
        $notDone = $roster->where('last_status', HomeworkCheckStatus::NotDone->label())->count();
        $unmarked = max(0, $total - $done - $notDone);

        return [
            'total' => $total,
            'done' => $done,
            'not_done' => $notDone,
            'unmarked' => $unmarked,
            'done_pct' => $total > 0 ? (int) round(($done / $total) * 100) : 0,
        ];
    }

    /**
     * Students × subjects grid for one class/date (Phy / Chem / Maths in one screen).
     *
     * @return array{
     *     subjects: list<array{id: int, label: string}>,
     *     students: list<array{
     *         id: int,
     *         name: string,
     *         mobile: ?string,
     *         not_done_week: int,
     *         cells: array<int, array{
     *             status: ?string,
     *             notify: ?string,
     *             check_id: ?int,
     *             can_resend: bool
     *         }>
     *     }>,
     *     summary: array{total: int, done: int, not_done: int, unmarked: int, done_pct: int}
     * }
     */
    public function multiSubjectGridForBatch(
        User $user,
        int $batchId,
        ?string $checkedOn = null,
        ?string $search = null,
    ): array {
        $checkedOnDate = $this->normalizeCheckedOn($checkedOn);
        $subjectOptions = $this->subjectOptionsForBatch($user, $batchId);

        $subjects = collect($subjectOptions)
            ->map(fn (string $label, int|string $id): array => [
                'id' => (int) $id,
                'label' => $label,
            ])
            ->values()
            ->all();

        $subjectIds = collect($subjects)->pluck('id')->all();

        $students = $this->rosterForBatch($batchId, null, $search, $checkedOnDate)
            ->map(fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => (string) $row['name'],
                'mobile' => $row['mobile'] ?? null,
                'not_done_week' => (int) ($row['not_done_week'] ?? 0),
                'cells' => [],
            ])
            ->values();

        $checksByStudentSubject = collect();

        if ($subjectIds !== [] && $students->isNotEmpty()) {
            $checksByStudentSubject = HomeworkCheck::query()
                ->where('batch_id', $batchId)
                ->whereIn('course_subject_id', $subjectIds)
                ->whereDate('checked_on', $checkedOnDate)
                ->orderByDesc('id')
                ->get()
                ->groupBy(fn (HomeworkCheck $check): string => $check->student_id.'-'.$check->course_subject_id)
                ->map(fn (Collection $group): HomeworkCheck => $group->first());
        }

        $done = 0;
        $notDone = 0;
        $totalCells = 0;

        $students = $students->map(function (array $student) use (
            $subjects,
            $checksByStudentSubject,
            &$done,
            &$notDone,
            &$totalCells,
        ): array {
            $cells = [];

            foreach ($subjects as $subject) {
                $totalCells++;
                $key = $student['id'].'-'.$subject['id'];
                /** @var HomeworkCheck|null $latest */
                $latest = $checksByStudentSubject->get($key);

                $canResend = $latest !== null
                    && $latest->status === HomeworkCheckStatus::NotDone
                    && in_array($latest->notify_status, [
                        HomeworkCheckNotifyStatus::Failed,
                        HomeworkCheckNotifyStatus::Pending,
                    ], true);

                if ($latest?->status === HomeworkCheckStatus::Done) {
                    $done++;
                } elseif ($latest?->status === HomeworkCheckStatus::NotDone) {
                    $notDone++;
                }

                $cells[$subject['id']] = [
                    'status' => $latest?->status?->label(),
                    'notify' => $latest?->notify_status?->label(),
                    'check_id' => $latest?->id,
                    'can_resend' => $canResend,
                ];
            }

            $student['cells'] = $cells;

            return $student;
        })->all();

        $unmarked = max(0, $totalCells - $done - $notDone);

        return [
            'subjects' => $subjects,
            'students' => $students,
            'summary' => [
                'total' => $totalCells,
                'done' => $done,
                'not_done' => $notDone,
                'unmarked' => $unmarked,
                'done_pct' => $totalCells > 0 ? (int) round(($done / $totalCells) * 100) : 0,
            ],
        ];
    }

    /**
     * @return Collection<int, HomeworkCheck>
     */
    public function recentForBatch(int $batchId, int $limit = 15, ?string $checkedOn = null): Collection
    {
        $query = HomeworkCheck::query()
            ->where('batch_id', $batchId)
            ->with(['student', 'createdBy', 'homeworkAssignment']);

        if (filled($checkedOn)) {
            $query->whereDate('checked_on', $this->normalizeCheckedOn($checkedOn));
        }

        return $query
            ->latest('id')
            ->limit($limit)
            ->get();
    }

    /**
     * @return Collection<int, HomeworkCheck>
     */
    public function forStudent(int $studentId, int $limit = 50): Collection
    {
        return HomeworkCheck::query()
            ->where('student_id', $studentId)
            ->with(['batch', 'createdBy', 'homeworkAssignment'])
            ->latest('id')
            ->limit($limit)
            ->get();
    }
}

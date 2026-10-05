<?php

namespace App\Services;

use App\Enums\CrmPermission;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LicenseFeature;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class HomeworkCheckService
{
    public function __construct(
        protected HomeworkCheckWhatsAppService $whatsapp,
        protected BatchStaffAssignmentService $assignments,
        protected HomeworkStudentLinkService $studentLinks,
    ) {}

    /**
     * @return array<int, string>
     */
    public function userCanManageHomeworkDesk(User $user): bool
    {
        return CrmAccess::can($user, CrmPermission::HomeworkManage);
    }

    public function batchOptionsFor(User $user): array
    {
        if ($this->userCanManageHomeworkDesk($user)) {
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
        if ($this->userCanManageHomeworkDesk($user)) {
            return Batch::query()->whereKey($batchId)->exists();
        }

        return BatchStaffAssignment::query()
            ->where('user_id', $user->id)
            ->where('batch_id', $batchId)
            ->exists();
    }

    public function userCanAccessSubject(User $user, int $batchId, int $courseSubjectId): bool
    {
        if ($batchId < 1 || $courseSubjectId < 1) {
            return false;
        }

        return array_key_exists($courseSubjectId, $this->subjectOptionsForBatch($user, $batchId));
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
        $batch = Batch::query()->find($batchId);

        if (! $batch) {
            return [];
        }

        $subjects = $batch->activeSubjects()->get();

        if (! $this->userCanManageHomeworkDesk($user)) {
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
            ->mapWithKeys(function (CourseSubject $subject) use ($user): array {
                $label = $subject->name;

                if (! $this->userCanManageHomeworkDesk($user) && filled($user->name)) {
                    $label .= ' ('.$user->name.')';
                }

                return [$subject->id => $label];
            })
            ->all();
    }

    /**
     * One line per homework for this class and date.
     * A teacher only gets the homework they saved. The admin gets every teacher's homework.
     *
     * @return array<int, string>
     */
    public function checkChoicesFor(User $user, int $batchId, string $date): array
    {
        $date = $this->normalizeCheckedOn($date);

        $query = HomeworkAssignment::query()
            ->with(['courseSubject', 'submittedBy', 'createdBy'])
            ->where('batch_id', $batchId)
            ->whereDate('homework_date', $date)
            ->whereNotNull('course_subject_id');

        if (! $this->userCanManageHomeworkDesk($user)) {
            $query->where(function ($rows) use ($user): void {
                $rows->where('submitted_by_user_id', $user->id)
                    ->orWhere(function ($own) use ($user): void {
                        $own->whereNull('submitted_by_user_id')
                            ->where('created_by_user_id', $user->id);
                    });
            });
        }

        $choices = [];

        foreach ($query->orderBy('course_subject_id')->orderBy('id')->get() as $assignment) {
            $choices[(int) $assignment->id] = $assignment->teacherSubjectLabel();
        }

        return $choices;
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

    public function earliestCheckDate(): string
    {
        return now()->subDays(7)->toDateString();
    }

    public function latestCheckDate(): string
    {
        return now()->subDay()->toDateString();
    }

    public function checkDateAllowed(string $date): bool
    {
        return $date >= $this->earliestCheckDate() && $date <= $this->latestCheckDate();
    }

    public function assertCheckDateInWindow(string $date): void
    {
        if ($date > $this->latestCheckDate()) {
            throw ValidationException::withMessages([
                'check_date' => "Today's homework cannot be checked today. Check it from tomorrow.",
            ]);
        }

        if ($date < $this->earliestCheckDate()) {
            throw ValidationException::withMessages([
                'check_date' => 'You can check only the last 7 days of homework.',
            ]);
        }
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

        if (! $this->userCanAccessSubject($teacher, $batchId, $courseSubjectId)) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'You are not assigned to this subject.',
            ]);
        }

        $topic = trim($topic);

        if ($topic === '') {
            $topic = "Today's homework";
        }

        $checkedOnDate = $this->normalizeCheckedOn($checkedOn);
        $this->assertCheckDateInWindow($checkedOnDate);

        if (! $this->homeworkReadyToMark($batchId, $courseSubjectId, $checkedOnDate)) {
            throw ValidationException::withMessages([
                'status' => 'Admin has not approved this homework yet.',
            ]);
        }

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
        }

        if (! $assignment) {
            $onlyUserId = $this->userCanManageHomeworkDesk($teacher) ? null : (int) $teacher->id;
            $assignment = $this->approvedAssignmentFor($batchId, $courseSubjectId, $checkedOnDate, $onlyUserId);
        }

        if ($assignment && ! $this->userCanManageHomeworkDesk($teacher)) {
            $ownerId = (int) ($assignment->submitted_by_user_id ?: $assignment->created_by_user_id);

            if ($ownerId !== (int) $teacher->id) {
                throw ValidationException::withMessages([
                    'course_subject_id' => 'You can check only the homework you gave.',
                ]);
            }
        }

        if ($assignment && $topic === "Today's homework" && filled($assignment->title)) {
            $topic = trim((string) $assignment->title);
        }

        if (! $batch->subjects()->where('course_subjects.id', $subject->id)->exists()) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Subject is not selected for this section.',
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

        $existingQuery = HomeworkCheck::query()
            ->where('student_id', $student->id)
            ->where('batch_id', $batch->id)
            ->where('course_subject_id', $subject->id)
            ->whereDate('checked_on', $checkedOnDate);

        if ($assignment) {
            $existingQuery->where('homework_assignment_id', $assignment->id);
        }

        $existing = $existingQuery->orderByDesc('id')->first();

        if ($existing && $existing->status === $status) {
            return [
                'check' => $existing,
                'whatsapp' => [
                    'queued' => false,
                    'message' => $status === HomeworkCheckStatus::Done
                        ? 'Already marked Done.'
                        : ($existing->notify_status === HomeworkCheckNotifyStatus::Sent
                            ? 'Already marked Not Done. Message already shared with parents.'
                            : 'Already marked Not Done.'),
                ],
            ];
        }

        $checkAttributes = [
            'homework_assignment_id' => $assignment?->id ?? $existing?->homework_assignment_id,
            'subject_name' => $assignment?->teacherSubjectLabel() ?? $subject->name,
            'topic' => $topic,
            'status' => $status,
            'parent_mobile' => filled($student->mobile) ? (string) $student->mobile : null,
            'notify_status' => $status === HomeworkCheckStatus::Done
                ? HomeworkCheckNotifyStatus::NotRequired
                : HomeworkCheckNotifyStatus::Pending,
            'notified_at' => null,
        ];

        if ($existing) {
            $existing->update($checkAttributes);
            $check = $existing->fresh();
        } else {
            $check = HomeworkCheck::query()->create([
                'student_id' => $student->id,
                'batch_id' => $batch->id,
                'course_subject_id' => $subject->id,
                'checked_on' => $checkedOnDate,
                'created_by_user_id' => $teacher->id,
                ...$checkAttributes,
            ]);
        }

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
     * Ticked students are one result. Everyone else in the class gets the other result.
     * WhatsApp goes only to students marked Not Done.
     *
     * @param  list<int>  $selectedIds
     * @return array{done: int, not_done: int, whatsappQueued: int, whatsappFailed: int, errors: list<string>}
     */
    public function applySelection(
        User $teacher,
        int $batchId,
        int $courseSubjectId,
        array $selectedIds,
        string $choice,
        string $topic,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
    ): array {
        if (! in_array($choice, ['done', 'not_done'], true)) {
            throw ValidationException::withMessages([
                'choice' => 'Choose whether the ticked students have done the homework.',
            ]);
        }

        $classIds = $this->rosterForBatch($batchId, $courseSubjectId, null, $checkedOn, $homeworkAssignmentId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $selected = collect($selectedIds)
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => in_array($id, $classIds, true))
            ->unique()
            ->values()
            ->all();

        if ($selected === []) {
            throw ValidationException::withMessages([
                'student_id' => 'Tick at least one student in this class.',
            ]);
        }

        $rest = array_values(array_diff($classIds, $selected));
        $notDoneIds = $choice === 'not_done' ? $selected : $rest;
        $doneIds = $choice === 'done' ? $selected : $rest;

        $doneResult = $this->markMany(
            $teacher,
            $batchId,
            $doneIds,
            $courseSubjectId,
            $topic,
            HomeworkCheckStatus::Done,
            $checkedOn,
            $homeworkAssignmentId,
        );
        $notDoneResult = $this->markMany(
            $teacher,
            $batchId,
            $notDoneIds,
            $courseSubjectId,
            $topic,
            HomeworkCheckStatus::NotDone,
            $checkedOn,
            $homeworkAssignmentId,
        );

        return [
            'done' => $doneResult['marked'],
            'not_done' => $notDoneResult['marked'],
            'whatsappQueued' => $notDoneResult['whatsappQueued'],
            'whatsappFailed' => $notDoneResult['whatsappFailed'],
            'errors' => array_values(array_filter([
                ...$doneResult['errors'],
                ...$notDoneResult['errors'],
            ])),
        ];
    }

    /**
     * One student is Not Done. Students with no mark yet become Done.
     * Students already Done or Not Done stay as they are.
     *
     * @return array{done: int, whatsappQueued: bool, whatsappMessage: string}
     */
    public function markNotDoneAndCloseOpen(
        User $teacher,
        int $batchId,
        int $studentId,
        int $courseSubjectId,
        string $topic,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
    ): array {
        $roster = $this->rosterForBatch($batchId, $courseSubjectId, null, $checkedOn, $homeworkAssignmentId);
        $student = $roster->first(fn (array $row): bool => (int) $row['id'] === $studentId);

        if (! is_array($student)) {
            throw ValidationException::withMessages([
                'student_id' => 'Student is not in this class.',
            ]);
        }

        $openIds = $roster
            ->filter(fn (array $row): bool => blank($row['last_status']) && (int) $row['id'] !== $studentId)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        $notDoneResult = $this->mark(
            $teacher,
            $batchId,
            $studentId,
            $courseSubjectId,
            $topic,
            HomeworkCheckStatus::NotDone,
            $checkedOn,
            $homeworkAssignmentId,
        );
        $doneResult = $openIds === []
            ? ['marked' => 0]
            : $this->markMany(
                $teacher,
                $batchId,
                $openIds,
                $courseSubjectId,
                $topic,
                HomeworkCheckStatus::Done,
                $checkedOn,
                $homeworkAssignmentId,
            );

        return [
            'done' => (int) $doneResult['marked'],
            'whatsappQueued' => (bool) ($notDoneResult['whatsapp']['queued'] ?? false),
            'whatsappMessage' => (string) ($notDoneResult['whatsapp']['message'] ?? ''),
        ];
    }

    /**
     * When at least one student is already Not Done, students with no mark become Done.
     */
    public function closeOpenStudentsWhenAnyNotDone(
        User $teacher,
        int $batchId,
        int $courseSubjectId,
        string $topic,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
    ): int {
        $checkedOnDate = $this->normalizeCheckedOn($checkedOn);
        $this->assertCheckDateInWindow($checkedOnDate);

        $roster = $this->rosterForBatch($batchId, $courseSubjectId, null, $checkedOnDate, $homeworkAssignmentId);
        $hasNotDone = $roster->contains(
            fn (array $row): bool => ($row['status_key'] ?? null) === 'not_done',
        );

        if (! $hasNotDone) {
            return 0;
        }

        $openIds = $roster
            ->filter(fn (array $row): bool => blank($row['last_status']))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($openIds === []) {
            return 0;
        }

        return (int) $this->markMany(
            $teacher,
            $batchId,
            $openIds,
            $courseSubjectId,
            $topic,
            HomeworkCheckStatus::Done,
            $checkedOnDate,
            $homeworkAssignmentId,
        )['marked'];
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
     * @return array{queued: bool, message: string, check: HomeworkCheck, campaign_id: int|null}
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

        $outcome = $this->whatsapp->notifyNotDone($check->fresh(['student', 'batch.course']), $teacher, wait: false);

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
            'campaign_id' => $outcome['campaign_id'] ?? null,
        ];
    }

    /**
     * @return Collection<int, array{id: int, name: string, mobile: ?string, check_id: ?int, last_status: ?string, last_notify: ?string, can_resend: bool, not_done_week: int, link_tracked: bool, link_opened: bool, link_opened_at: ?string}>
     */
    public function rosterForBatch(
        int $batchId,
        ?int $courseSubjectId = null,
        ?string $search = null,
        ?string $checkedOn = null,
        ?int $homeworkAssignmentId = null,
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
                ->when($homeworkAssignmentId, fn ($query) => $query->where('homework_assignment_id', $homeworkAssignmentId))
                ->orderByDesc('id')
                ->get()
                ->unique('student_id')
                ->keyBy('student_id');
        }

        $rows = $query->get();
        $studentIds = $rows->pluck('student_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $notDoneWeek = $this->notDoneCountsThisWeek($studentIds);
        $linkStateByStudent = ($courseSubjectId && $courseSubjectId > 0)
            ? $this->studentLinks->openStateForClassSubjectDate($batchId, $courseSubjectId, $checkedOnDate)
            : [];

        return $rows
            ->map(function (BatchStudent $row) use ($latestByStudent, $notDoneWeek, $linkStateByStudent): ?array {
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

                $linkState = $linkStateByStudent[(int) $student->id] ?? null;

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'mobile' => $student->mobile,
                    'check_id' => $latest?->id,
                    'status_key' => $latest?->status?->value,
                    'last_status' => $latest?->status?->label(),
                    'last_notify' => $latest?->notify_status?->label(),
                    'parent_line' => $this->parentLine($latest),
                    'can_resend' => $canResend,
                    'not_done_week' => (int) ($notDoneWeek[$student->id] ?? 0),
                    'link_tracked' => is_array($linkState),
                    'link_opened' => (bool) ($linkState['opened'] ?? false),
                    'link_opened_at' => $linkState['at'] ?? null,
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

    protected function parentLine(?HomeworkCheck $check): ?string
    {
        if ($check?->status !== HomeworkCheckStatus::NotDone) {
            return null;
        }

        return match ($check->notify_status) {
            HomeworkCheckNotifyStatus::Sent => 'Message shared with parents',
            HomeworkCheckNotifyStatus::Failed => 'Message was not shared',
            default => 'Message to parents is waiting',
        };
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

    public function homeworkWasGiven(int $batchId, int $courseSubjectId, ?string $checkedOn): bool
    {
        if ($batchId < 1 || $courseSubjectId < 1) {
            return false;
        }

        return HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $courseSubjectId)
            ->whereDate('homework_date', $this->normalizeCheckedOn($checkedOn))
            ->exists();
    }

    protected function approvedAssignmentFor(int $batchId, int $courseSubjectId, string $checkedOnDate, ?int $onlyUserId = null): ?HomeworkAssignment
    {
        return HomeworkAssignment::query()
            ->with(['courseSubject', 'submittedBy', 'createdBy'])
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $courseSubjectId)
            ->whereDate('homework_date', $checkedOnDate)
            ->whereIn('status', [
                HomeworkAssignmentStatus::Approved->value,
                HomeworkAssignmentStatus::Sent->value,
            ])
            ->when($onlyUserId, function ($query) use ($onlyUserId): void {
                $query->where(function ($rows) use ($onlyUserId): void {
                    $rows->where('submitted_by_user_id', $onlyUserId)
                        ->orWhere(function ($own) use ($onlyUserId): void {
                            $own->whereNull('submitted_by_user_id')
                                ->where('created_by_user_id', $onlyUserId);
                        });
                });
            })
            ->orderByDesc('id')
            ->first();
    }

    /**
     * @return array{subject_id: int, assignment_id: ?int}
     */
    public function resolveCheckSelection(User $user, int $batchId, int $selectedId, string $date): array
    {
        $choices = $this->checkChoicesFor($user, $batchId, $date);

        if (isset($choices[$selectedId])) {
            $assignment = HomeworkAssignment::query()->find($selectedId);

            return [
                'subject_id' => (int) ($assignment?->course_subject_id ?? 0),
                'assignment_id' => $assignment ? (int) $assignment->id : null,
            ];
        }

        return [
            'subject_id' => $selectedId,
            'assignment_id' => null,
        ];
    }

    public function assignmentReadyToMark(int $assignmentId, ?string $checkedOn): bool
    {
        $date = $this->normalizeCheckedOn($checkedOn);

        if (! $this->checkDateAllowed($date)) {
            return false;
        }

        $assignment = HomeworkAssignment::query()->find($assignmentId);

        if (! $assignment || $assignment->homework_date?->toDateString() !== $date) {
            return false;
        }

        return in_array($assignment->status, [
            HomeworkAssignmentStatus::Approved,
            HomeworkAssignmentStatus::Sent,
        ], true);
    }

    public function homeworkReadyToMark(int $batchId, int $courseSubjectId, ?string $checkedOn): bool
    {
        if ($batchId < 1 || $courseSubjectId < 1) {
            return false;
        }

        $date = $this->normalizeCheckedOn($checkedOn);

        if (! $this->checkDateAllowed($date)) {
            return false;
        }

        return HomeworkAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $courseSubjectId)
            ->whereDate('homework_date', $date)
            ->whereIn('status', [
                HomeworkAssignmentStatus::Approved->value,
                HomeworkAssignmentStatus::Sent->value,
            ])
            ->exists();
    }

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

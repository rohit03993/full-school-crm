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
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Support\FeatureGate;
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

        $batch = Batch::query()->with('course')->findOrFail($batchId);
        $student = Student::query()->findOrFail($studentId);
        $subject = CourseSubject::query()->findOrFail($courseSubjectId);

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
            'subject_name' => $subject->displayLabel(),
            'topic' => $topic,
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
     * @return Collection<int, array{id: int, name: string, mobile: ?string, last_status: ?string, last_notify: ?string}>
     */
    public function rosterForBatch(int $batchId, ?int $courseSubjectId = null, ?string $search = null): Collection
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

        $latestByStudent = collect();

        if ($courseSubjectId) {
            $latestByStudent = HomeworkCheck::query()
                ->where('batch_id', $batchId)
                ->where('course_subject_id', $courseSubjectId)
                ->whereDate('created_at', now()->toDateString())
                ->orderByDesc('id')
                ->get()
                ->unique('student_id')
                ->keyBy('student_id');
        }

        return $query
            ->get()
            ->map(function (BatchStudent $row) use ($latestByStudent): ?array {
                $student = $row->student;
                if (! $student) {
                    return null;
                }

                /** @var HomeworkCheck|null $latest */
                $latest = $latestByStudent->get($student->id);

                return [
                    'id' => $student->id,
                    'name' => $student->name,
                    'mobile' => $student->mobile,
                    'last_status' => $latest?->status?->label(),
                    'last_notify' => $latest?->notify_status?->label(),
                ];
            })
            ->filter()
            ->sortBy('name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();
    }

    /**
     * @return Collection<int, HomeworkCheck>
     */
    public function recentForBatch(int $batchId, int $limit = 15): Collection
    {
        return HomeworkCheck::query()
            ->where('batch_id', $batchId)
            ->with(['student', 'createdBy'])
            ->latest()
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
            ->with(['batch', 'createdBy'])
            ->latest()
            ->limit($limit)
            ->get();
    }
}

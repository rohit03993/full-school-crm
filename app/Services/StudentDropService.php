<?php

namespace App\Services;

use App\Enums\BatchStaffRole;
use App\Enums\EnrollmentStatus;
use App\Enums\InactiveReason;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\CourseSubject;
use App\Models\Student;
use App\Models\StudentDrop;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class StudentDropService
{
    public function __construct(
        protected AuditService $audit,
    ) {}

    public function canDrop(?User $user): bool
    {
        if (! $user?->is_active) {
            return false;
        }

        return $user->hasRole(RoleName::SuperAdmin->value)
            || $user->hasRole(StaffJobRole::AcademicCoordinator->value);
    }

    public function drop(
        Student $student,
        User $staff,
        string $reason,
        Carbon|string|null $inactiveAt = null,
        ?string $reasonType = null,
        ?int $courseSubjectId = null,
    ): StudentDrop {
        if (! $this->canDrop($staff)) {
            throw ValidationException::withMessages([
                'reason' => 'Only Super Admin or Academic coordinator can mark a student inactive.',
            ]);
        }

        $reason = trim($reason);
        $inactiveAt = $this->inactiveMoment($inactiveAt);
        $type = $this->reasonType($reasonType);

        $rules = [
            'reason' => ['required', 'string', 'max:1000'],
            'inactive_at' => ['required', 'date'],
        ];

        if ($type instanceof InactiveReason) {
            $rules['reason'][] = 'min:10';
        }

        Validator::make(
            ['reason' => $reason, 'inactive_at' => $inactiveAt->toDateTimeString()],
            $rules,
            [
                'reason.required' => 'Write a short summary of why this student is inactive.',
                'reason.min' => 'Write a short summary. A few words are enough.',
            ],
        )->validate();

        if ($inactiveAt->greaterThan(now()->addMinute())) {
            throw ValidationException::withMessages([
                'inactive_at' => 'Inactive from cannot be a future date or time.',
            ]);
        }

        return DB::transaction(function () use ($student, $staff, $reason, $inactiveAt, $type, $courseSubjectId): StudentDrop {
            $locked = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === StudentStatus::Dropped) {
                throw ValidationException::withMessages([
                    'reason' => 'This student is already inactive.',
                ]);
            }

            $enrollment = $locked->activeEnrollment()->lockForUpdate()->first();

            if (! $enrollment || $locked->status !== StudentStatus::Enrolled) {
                throw ValidationException::withMessages([
                    'reason' => 'Only an enrolled student can be marked inactive.',
                ]);
            }

            if ($enrollment->enrolled_at && $inactiveAt->lt($enrollment->enrolled_at)) {
                throw ValidationException::withMessages([
                    'inactive_at' => 'Inactive from cannot be before the student joined.',
                ]);
            }

            $seat = $locked->activeBatchStudent()->lockForUpdate()->first();
            $subject = $type === InactiveReason::Academics
                ? $this->subjectForDrop($seat?->batch_id, $courseSubjectId)
                : null;

            if ($seat) {
                $seat->update(['is_active' => false]);
            }

            $enrollment->update([
                'status' => EnrollmentStatus::Dropped,
            ]);

            $locked->update([
                'status' => StudentStatus::Dropped,
            ]);

            $drop = StudentDrop::query()->create([
                'student_id' => $locked->id,
                'enrollment_id' => $enrollment->id,
                'batch_id' => $seat?->batch_id,
                'reason' => $reason,
                'reason_type' => $type?->value,
                'course_subject_id' => $subject['id'] ?? null,
                'subject_name' => $subject['name'] ?? null,
                'subject_teacher_user_id' => $subject['teacher_user_id'] ?? null,
                'subject_teacher_name' => $subject['teacher_name'] ?? null,
                'dropped_by_user_id' => $staff->id,
                'dropped_at' => $inactiveAt,
            ]);

            $this->audit->log(
                'student.dropped',
                $locked,
                ['status' => StudentStatus::Enrolled->value],
                [
                    'status' => StudentStatus::Dropped->value,
                    'batch_id' => $seat?->batch_id,
                ],
                $reason,
                $staff,
            );

            return $drop;
        });
    }

    public function reinstate(Student $student, User $staff): Student
    {
        if (! $this->canDrop($staff)) {
            throw ValidationException::withMessages([
                'reason' => 'Only Super Admin or Academic coordinator can make a student active again.',
            ]);
        }

        return DB::transaction(function () use ($student, $staff): Student {
            $locked = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StudentStatus::Dropped) {
                throw ValidationException::withMessages([
                    'reason' => 'Only an inactive student can be made active again.',
                ]);
            }

            $drop = $locked->drops()->whereNull('reactivated_at')->latest('dropped_at')->first()
                ?? $locked->latestDrop()->first();
            $enrollment = $drop?->enrollment()->lockForUpdate()->first()
                ?? $locked->activeEnrollment()->lockForUpdate()->first();

            if (! $enrollment) {
                throw ValidationException::withMessages([
                    'reason' => 'This student has no admission to make active again.',
                ]);
            }

            $enrollment->update([
                'status' => EnrollmentStatus::Enrolled,
            ]);

            $locked->update([
                'status' => StudentStatus::Enrolled,
            ]);

            $seat = $this->seatToRestore($locked, $drop?->batch_id);

            if ($drop && $drop->reactivated_at === null) {
                $drop->update([
                    'reactivated_at' => now(),
                    'reactivated_by_user_id' => $staff->id,
                ]);
            }

            if ($seat && Batch::query()->whereKey($seat->batch_id)->exists()) {
                BatchStudent::query()
                    ->where('student_id', $locked->id)
                    ->where('is_active', true)
                    ->whereKeyNot($seat->id)
                    ->update(['is_active' => false]);

                $seat->update(['is_active' => true]);
            }

            $this->audit->log(
                'student.reactivated',
                $locked,
                ['status' => StudentStatus::Dropped->value],
                [
                    'status' => StudentStatus::Enrolled->value,
                    'batch_id' => $seat?->batch_id,
                ],
                null,
                $staff,
            );

            return $locked->fresh();
        });
    }

    protected function seatToRestore(Student $student, ?int $batchId): ?BatchStudent
    {
        if ($batchId) {
            $match = BatchStudent::query()
                ->where('student_id', $student->id)
                ->where('batch_id', $batchId)
                ->lockForUpdate()
                ->first();

            if ($match) {
                return $match;
            }
        }

        return BatchStudent::query()
            ->where('student_id', $student->id)
            ->where('is_active', false)
            ->orderByDesc('id')
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array<int, string>
     */
    public function subjectChoices(Student $student): array
    {
        $batch = $student->activeBatchStudent?->batch;

        if (! $batch) {
            return [];
        }

        return $batch->activeSubjects()
            ->pluck('course_subjects.name', 'course_subjects.id')
            ->all();
    }

    public function subjectTeacherLabel(Student $student, ?int $subjectId): string
    {
        $batchId = $student->activeBatchStudent?->batch_id;

        if (! $batchId || ! $subjectId) {
            return 'No teacher assigned';
        }

        $names = $this->subjectTeachers($batchId, $subjectId)
            ->map(fn (BatchStaffAssignment $row): ?string => $row->user?->name)
            ->filter()
            ->unique()
            ->values();

        return $names->isEmpty() ? 'No teacher assigned' : $names->implode(', ');
    }

    protected function reasonType(?string $reasonType): ?InactiveReason
    {
        if ($reasonType === null || $reasonType === '') {
            return null;
        }

        $type = InactiveReason::tryFrom($reasonType);

        if (! $type) {
            throw ValidationException::withMessages([
                'reason_type' => 'Pick a reason from the list.',
            ]);
        }

        return $type;
    }

    /**
     * @return array{id: int, name: string, teacher_user_id: ?int, teacher_name: ?string}
     */
    protected function subjectForDrop(?int $batchId, ?int $subjectId): array
    {
        if (! $batchId || ! $subjectId) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Pick the subject this student was struggling with.',
            ]);
        }

        $subject = Batch::query()->whereKey($batchId)->first()
            ?->activeSubjects()
            ->where('course_subjects.id', $subjectId)
            ->first();

        if (! $subject instanceof CourseSubject) {
            throw ValidationException::withMessages([
                'course_subject_id' => 'Pick a subject from this student’s class.',
            ]);
        }

        $teachers = $this->subjectTeachers($batchId, $subject->id);
        $firstTeacher = $teachers->first();
        $names = $teachers
            ->map(fn (BatchStaffAssignment $row): ?string => $row->user?->name)
            ->filter()
            ->unique()
            ->values();

        return [
            'id' => $subject->id,
            'name' => $subject->name,
            'teacher_user_id' => $firstTeacher?->user_id,
            'teacher_name' => $names->isEmpty() ? null : $names->implode(', '),
        ];
    }

    /**
     * @return Collection<int, BatchStaffAssignment>
     */
    protected function subjectTeachers(int $batchId, int $subjectId): Collection
    {
        return BatchStaffAssignment::query()
            ->where('batch_id', $batchId)
            ->where('course_subject_id', $subjectId)
            ->where('role', BatchStaffRole::SubjectTeacher)
            ->with('user:id,name')
            ->orderBy('id')
            ->get();
    }

    protected function inactiveMoment(Carbon|string|null $inactiveAt): Carbon
    {
        if ($inactiveAt instanceof Carbon) {
            return $inactiveAt->copy()->timezone(config('app.timezone'));
        }

        if (filled($inactiveAt)) {
            return Carbon::parse($inactiveAt, config('app.timezone'));
        }

        return now();
    }
}

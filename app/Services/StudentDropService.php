<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Student;
use App\Models\StudentDrop;
use App\Models\User;
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

    public function drop(Student $student, User $staff, string $reason): StudentDrop
    {
        if (! $this->canDrop($staff)) {
            throw ValidationException::withMessages([
                'reason' => 'Only Super Admin or Academic coordinator can mark a student inactive.',
            ]);
        }

        $reason = trim($reason);

        Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'Enter the reason for marking this student inactive.'],
        )->validate();

        return DB::transaction(function () use ($student, $staff, $reason): StudentDrop {
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

            $seat = $locked->activeBatchStudent()->lockForUpdate()->first();

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
                'dropped_by_user_id' => $staff->id,
                'dropped_at' => now(),
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

            $drop = $locked->latestDrop()->first();
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
}

<?php

namespace App\Services;

use App\Enums\EnrollmentStatus;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
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
                'reason' => 'Only Super Admin or Academic coordinator can drop a student.',
            ]);
        }

        $reason = trim($reason);

        Validator::make(
            ['reason' => $reason],
            ['reason' => ['required', 'string', 'max:1000']],
            ['reason.required' => 'Enter the reason for dropping this student.'],
        )->validate();

        return DB::transaction(function () use ($student, $staff, $reason): StudentDrop {
            $locked = Student::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === StudentStatus::Dropped) {
                throw ValidationException::withMessages([
                    'reason' => 'This student is already dropped.',
                ]);
            }

            $enrollment = $locked->activeEnrollment()->lockForUpdate()->first();

            if (! $enrollment || $locked->status !== StudentStatus::Enrolled) {
                throw ValidationException::withMessages([
                    'reason' => 'Only an enrolled student can be dropped.',
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
}

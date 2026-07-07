<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnrollmentPlacementService
{
    public function __construct(
        protected AuditService $audit,
        protected BatchService $batches,
        protected CourseFeeSyncService $courseFees,
    ) {}

    public function updateCourse(Enrollment $enrollment, int $courseId, User $staff): Enrollment
    {
        if ($enrollment->course_id === $courseId) {
            return $enrollment;
        }

        $course = Course::query()->findOrFail($courseId);
        $feeStructure = $enrollment->feeStructure;

        if ($feeStructure) {
            $catalogFee = round((float) $course->fee, 2);
            $paid = round((float) $feeStructure->paid_amount, 2);
            $discount = round((float) $feeStructure->discount_amount, 2);
            $miscTotal = $feeStructure->miscChargesTotal();
            $newNet = round($catalogFee - $discount + $miscTotal, 2);

            if ($newNet < $paid) {
                throw ValidationException::withMessages([
                    'course_id' => 'The new course fee is too low for fees already collected. Use Adjust Fees first.',
                ]);
            }
        }

        return DB::transaction(function () use ($enrollment, $course, $staff, $feeStructure): Enrollment {
            $enrollment->loadMissing(['admission.enquiry', 'feeStructure', 'student.activeBatchStudent']);
            $oldCourseId = $enrollment->course_id;

            $enrollment->update(['course_id' => $course->id]);

            $admission = $enrollment->admission;

            if ($admission) {
                $admission->update([
                    'course_id' => $course->id,
                    'course_fee' => $course->fee,
                ]);

                $admission->enquiry?->update(['course_id' => $course->id]);
            }

            $student = $enrollment->student;
            $activeBatch = $student?->activeBatchStudent;

            if ($activeBatch && $activeBatch->batch?->course_id !== $course->id) {
                $activeBatch->update(['is_active' => false]);
            }

            if ($feeStructure) {
                $this->courseFees->syncFeeStructureToCatalog($feeStructure->fresh(), $course, $staff);
            }

            $this->audit->log(
                action: 'Enrollment Course Updated',
                auditable: $enrollment,
                oldValues: ['course_id' => $oldCourseId],
                newValues: ['course_id' => $course->id, 'course_name' => $course->name],
                user: $staff,
            );

            return $enrollment->fresh(['course', 'feeStructure']);
        });
    }

    public function updateBatch(Student $student, ?int $batchId, User $staff): void
    {
        if (! $batchId) {
            return;
        }

        $batch = Batch::query()
            ->whereKey($batchId)
            ->where('status', BatchStatus::Active)
            ->first();

        if (! $batch) {
            throw ValidationException::withMessages([
                'batch_id' => 'The selected batch is no longer available. Choose another batch or leave it unassigned.',
            ]);
        }

        $this->batches->assign($student, $batch, $staff);
    }
}

<?php

namespace App\Services;

use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\FeeStructure;
use App\Models\User;

class CourseFeeSyncService
{
    public function __construct(
        protected FeeStructureService $feeStructures,
        protected AdmissionFeePlanService $feePlans,
    ) {}

    /**
     * Push a new catalog course fee to every active enrollment on this course.
     */
    public function syncCourseToActiveEnrollments(Course $course, ?User $actor = null): int
    {
        $synced = 0;

        Enrollment::query()
            ->where('course_id', $course->id)
            ->where('is_active', true)
            ->whereHas('feeStructure')
            ->with(['feeStructure', 'course'])
            ->orderBy('id')
            ->each(function (Enrollment $enrollment) use ($course, $actor, &$synced): void {
                if ($this->syncFeeStructureToCatalog($enrollment->feeStructure, $course, $actor)) {
                    $synced++;
                }
            });

        return $synced;
    }

    /**
     * Align one student's fee structure with the course catalog when they differ.
     */
    public function syncToCatalogIfNeeded(FeeStructure $feeStructure, Course $course, ?User $actor = null): bool
    {
        return $this->syncFeeStructureToCatalog($feeStructure, $course, $actor);
    }

    public function syncFeeStructureToCatalog(FeeStructure $feeStructure, Course $course, ?User $actor = null): bool
    {
        $feeStructure->loadMissing(['miscCharges', 'installments', 'enrollment']);

        $catalogFee = round((float) $course->fee, 2);
        $currentFee = round((float) $feeStructure->course_fee, 2);

        if ($catalogFee <= 0 || abs($catalogFee - $currentFee) <= 0.01) {
            return false;
        }

        $discount = round((float) $feeStructure->discount_amount, 2);
        $miscTotal = $feeStructure->miscChargesTotal();
        $paid = round((float) $feeStructure->paid_amount, 2);
        $newNet = round($catalogFee - $discount + $miscTotal, 2);

        if ($newNet < $paid) {
            return false;
        }

        $newPending = round($newNet - $paid, 2);
        $installmentPlan = $newPending > 0
            ? $this->feePlans->normalizeInstallmentPlan(
                AdjustFeeStructureFormSchema::pendingInstallmentPlan($feeStructure, $newPending),
            )
            : [];

        if (! $actor) {
            return false;
        }

        $this->feeStructures->updateByAdmin($feeStructure, [
            'course_fee' => $catalogFee,
            'discount_amount' => $discount,
            'reason' => sprintf(
                'Course catalog fee updated to ₹%s. Existing discount kept at ₹%s.',
                number_format($catalogFee, 2),
                number_format($discount, 2),
            ),
            'reschedule_installments' => true,
            'installment_plan' => $installmentPlan,
        ], $actor);

        return true;
    }
}

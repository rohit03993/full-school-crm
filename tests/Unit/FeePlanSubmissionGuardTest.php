<?php

namespace Tests\Unit;

use App\Models\FeeStructure;
use App\Support\FeePlanSubmissionGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FeePlanSubmissionGuardTest extends TestCase
{
    use RefreshDatabase;
    public function test_can_submit_convert_allows_when_installment_plan_not_used(): void
    {
        $this->assertTrue(FeePlanSubmissionGuard::canSubmitConvert([
            'use_installment_plan' => false,
        ]));
    }

    public function test_can_submit_adjust_fees_allows_when_reschedule_off(): void
    {
        $feeStructure = new FeeStructure([
            'course_fee' => 100000,
            'discount_amount' => 0,
            'paid_amount' => 0,
        ]);
        $feeStructure->setRelation('miscCharges', collect());

        $this->assertTrue(FeePlanSubmissionGuard::canSubmitAdjustFees([
            'reschedule_installments' => false,
            'installment_plan' => [],
        ], $feeStructure));
    }

    public function test_can_submit_adjust_fees_blocks_unallocated_pending_schedule(): void
    {
        $feeStructure = new FeeStructure([
            'course_fee' => 100000,
            'discount_amount' => 0,
            'paid_amount' => 50000,
        ]);
        $feeStructure->setRelation('miscCharges', collect());

        $this->assertFalse(FeePlanSubmissionGuard::canSubmitAdjustFees([
            'course_fee' => 100000,
            'discount_amount' => 0,
            'reschedule_installments' => true,
            'installment_plan' => [
                ['amount' => '20000'],
            ],
        ], $feeStructure));
    }

    public function test_can_submit_adjust_fees_blocks_when_discount_reason_missing(): void
    {
        $feeStructure = new FeeStructure([
            'course_fee' => 100000,
            'discount_amount' => 0,
            'paid_amount' => 0,
        ]);
        $feeStructure->setRelation('miscCharges', collect());

        $this->assertFalse(FeePlanSubmissionGuard::canSubmitAdjustFees([
            'course_fee' => 100000,
            'discount_mode' => 'amount',
            'discount_adjustment' => 5000,
            'reschedule_installments' => false,
            'reason' => '',
        ], $feeStructure));
    }

    public function test_can_submit_convert_blocks_zero_course_fee(): void
    {
        $course = \App\Models\Course::query()->create([
            'name' => 'Zero Fee Class',
            'code' => 'ZERO-01',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 0,
            'status' => \App\Enums\CourseStatus::Active,
        ]);

        $this->assertFalse(FeePlanSubmissionGuard::canSubmitConvert([
            'course_id' => $course->id,
            'discount_amount' => 0,
            'use_installment_plan' => false,
        ]));
    }

    public function test_can_submit_convert_blocks_unbalanced_installment_plan(): void
    {
        $course = \App\Models\Course::query()->create([
            'name' => 'Paid Class',
            'code' => 'PAID-01',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 100000,
            'status' => \App\Enums\CourseStatus::Active,
        ]);

        $this->assertFalse(FeePlanSubmissionGuard::canSubmitConvert([
            'course_id' => $course->id,
            'discount_amount' => 0,
            'use_installment_plan' => true,
            'installment_plan' => [
                ['amount' => '30000'],
            ],
        ]));
    }
}

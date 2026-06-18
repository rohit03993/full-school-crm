<?php

namespace Tests\Unit;

use App\Models\FeeStructure;
use App\Support\FeePlanSubmissionGuard;
use Tests\TestCase;

class FeePlanSubmissionGuardTest extends TestCase
{
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
}

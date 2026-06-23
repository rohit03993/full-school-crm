<?php

namespace Tests\Unit;

use App\Filament\Forms\AdjustFeeStructureFormSchema;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class AdjustFeeStructureFormSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_installment_plan_matches_fee_structure_pending_when_rows_are_stale(): void
    {
        $feeStructure = new FeeStructure([
            'course_fee' => 120000,
            'discount_amount' => 5000,
            'net_fee' => 115000,
            'paid_amount' => 15000,
            'pending_amount' => 100000,
        ]);

        $feeStructure->setRelation('installments', new Collection([
            new FeeInstallment([
                'label' => 'Installment 1',
                'amount' => 15000,
                'paid_amount' => 15000,
                'pending_amount' => 0,
            ]),
            new FeeInstallment([
                'label' => 'Installment 2',
                'amount' => 95000,
                'paid_amount' => 0,
                'pending_amount' => 95000,
            ]),
        ]));

        $plan = AdjustFeeStructureFormSchema::pendingInstallmentPlan($feeStructure);

        $this->assertCount(1, $plan);
        $this->assertSame('100000', $plan[0]['amount']);
        $this->assertSame('Installment 2', $plan[0]['label']);
    }

    public function test_additional_discount_reduces_net_from_current_position_not_course_fee(): void
    {
        $feeStructure = new FeeStructure([
            'course_fee' => 120000,
            'discount_amount' => 5000,
            'net_fee' => 115000,
            'paid_amount' => 10000,
            'pending_amount' => 105000,
        ]);

        $mounted = [
            'course_fee' => 120000,
            'additional_discount' => 5000,
        ];

        $this->assertSame(110000.0, AdjustFeeStructureFormSchema::previewNetFromMounted($feeStructure, $mounted, 0.0));
        $this->assertSame(100000.0, AdjustFeeStructureFormSchema::scheduleTargetFromMounted($feeStructure, $mounted, 0.0));

        $resolved = AdjustFeeStructureFormSchema::resolveForSave($feeStructure, [
            'course_fee' => 120000,
            'additional_discount' => 5000,
            'reason' => 'Sibling discount',
        ]);

        $this->assertSame(10000.0, $resolved['discount_amount']);
    }
}

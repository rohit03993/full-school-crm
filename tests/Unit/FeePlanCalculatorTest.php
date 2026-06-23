<?php

namespace Tests\Unit;

use App\Support\FeePlanCalculator;
use Tests\TestCase;

class FeePlanCalculatorTest extends TestCase
{
    public function test_new_installment_row_prefills_remaining_balance(): void
    {
        $existing = [
            ['label' => 'Installment 1', 'amount' => '30000', 'due_date' => '2026-06-21'],
        ];

        $row = FeePlanCalculator::newInstallmentRow($existing, 95000, 1);

        $this->assertSame('Installment 2', $row['label']);
        $this->assertSame('65000', $row['amount']);
        $this->assertSame('2026-07-21', $row['due_date']);
    }

    public function test_next_due_date_defaults_to_today_for_first_row(): void
    {
        $this->assertSame(now()->toDateString(), FeePlanCalculator::nextDueDate([]));
    }

    public function test_unallocated_warning_message_when_remaining_balance_exists(): void
    {
        $message = FeePlanCalculator::unallocatedWarningMessage(95000, [
            ['amount' => '30000'],
            ['amount' => '20'],
        ]);

        $this->assertNotNull($message);
        $this->assertStringContainsString('64,980.00', $message);
    }

    public function test_is_fully_allocated_within_one_paisa(): void
    {
        $rows = [
            ['amount' => '47500'],
            ['amount' => '47500'],
        ];

        $this->assertTrue(FeePlanCalculator::isFullyAllocated(95000, $rows));
    }

    public function test_sort_and_renumber_installment_plan_orders_by_due_date(): void
    {
        $sorted = FeePlanCalculator::sortAndRenumberInstallmentPlan([
            ['label' => 'Installment 1', 'amount' => '10000', 'due_date' => '2026-06-18'],
            ['label' => 'Installment 2', 'amount' => '20000', 'due_date' => '2026-07-18'],
            ['label' => 'Installment 3', 'amount' => '35000', 'due_date' => '2026-04-01'],
        ]);

        $this->assertSame('Installment 1', $sorted[0]['label']);
        $this->assertSame('2026-04-01', $sorted[0]['due_date']);
        $this->assertSame('Installment 2', $sorted[1]['label']);
        $this->assertSame('2026-06-18', $sorted[1]['due_date']);
        $this->assertSame('Installment 3', $sorted[2]['label']);
        $this->assertSame('2026-07-18', $sorted[2]['due_date']);
    }

    public function test_auto_fill_single_empty_row(): void
    {
        $rows = [
            ['amount' => '30000'],
            ['amount' => ''],
        ];

        $filled = FeePlanCalculator::autoFillSingleEmptyRow($rows, 95000);

        $this->assertSame('65000', $filled[1]['amount']);
    }

    public function test_auto_fill_skips_when_multiple_empty_rows(): void
    {
        $rows = [
            ['amount' => ''],
            ['amount' => ''],
        ];

        $filled = FeePlanCalculator::autoFillSingleEmptyRow($rows, 95000);

        $this->assertSame($rows, $filled);
    }

    public function test_fill_balance_on_last_row_works_with_string_repeater_keys(): void
    {
        $rows = [
            'a1b2c3d4' => ['label' => 'Installment 1', 'amount' => '15000'],
            'e5f6g7h8' => ['label' => 'Installment 2', 'amount' => '95000'],
        ];

        $filled = FeePlanCalculator::fillBalanceOnLastRow($rows, 100000);

        $this->assertSame('15000', $filled['a1b2c3d4']['amount']);
        $this->assertSame('85000', $filled['e5f6g7h8']['amount']);
    }
}

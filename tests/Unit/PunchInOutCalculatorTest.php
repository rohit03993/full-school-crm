<?php

namespace Tests\Unit;

use App\Services\Punch\PunchInOutCalculator;
use Tests\TestCase;

class PunchInOutCalculatorTest extends TestCase
{
    public function test_first_punch_is_in_and_second_valid_gap_is_out(): void
    {
        $calculator = new PunchInOutCalculator;

        $punches = collect([
            (object) ['punch_date' => '2026-06-20', 'punch_time' => '09:00:00', 'is_manual' => 0],
            (object) ['punch_date' => '2026-06-20', 'punch_time' => '09:00:05', 'is_manual' => 0],
            (object) ['punch_date' => '2026-06-20', 'punch_time' => '17:00:00', 'is_manual' => 0],
        ]);

        $this->assertSame('IN', $calculator->stateForPunch($punches, '09:00:00', '2026-06-20'));
        $this->assertSame('OUT', $calculator->stateForPunch($punches, '17:00:00', '2026-06-20'));

        [$daily] = $calculator->computeInOut($punches);

        $this->assertCount(1, $daily['2026-06-20']);
        $this->assertSame('09:00:00', $daily['2026-06-20'][0]['in']);
        $this->assertSame('17:00:00', $daily['2026-06-20'][0]['out']);
    }
}

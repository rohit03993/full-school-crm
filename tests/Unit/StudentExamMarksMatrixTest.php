<?php

namespace Tests\Unit;

use App\Support\StudentExamMarksMatrix;
use Tests\TestCase;

class StudentExamMarksMatrixTest extends TestCase
{
    public function test_format_total_includes_percentage(): void
    {
        $display = StudentExamMarksMatrix::formatTotal(181, 300, 60.33, true);

        $this->assertStringContainsString('181 / 300', $display);
        $this->assertStringContainsString('60.33%', $display);
    }

    public function test_format_percentage_from_marks_and_max(): void
    {
        $this->assertSame(50.0, StudentExamMarksMatrix::percentage(50, 100));
        $this->assertSame('50%', StudentExamMarksMatrix::formatPercentage(50, 100));
        $this->assertSame('—', StudentExamMarksMatrix::formatPercentage(null, 100));
    }

    public function test_percentage_includes_negative_obtained_marks(): void
    {
        $this->assertSame(-10.0, StudentExamMarksMatrix::percentage(-10, 100));
        $this->assertSame(36.67, StudentExamMarksMatrix::percentage(110, 300));
        $this->assertSame('-10%', StudentExamMarksMatrix::formatPercentage(-10, 100));
        $this->assertSame('-10 / 300 (-3.33%)', StudentExamMarksMatrix::formatTotal(-10, 300, -3.33, true));
    }

    public function test_obtained_range_allows_negative_up_to_max_magnitude(): void
    {
        $this->assertSame(-100.0, StudentExamMarksMatrix::obtainedFloor(100));
        $this->assertNull(StudentExamMarksMatrix::obtainedRangeError(-10, 100));
        $this->assertNull(StudentExamMarksMatrix::obtainedRangeError(-100, 100));
        $this->assertNull(StudentExamMarksMatrix::obtainedRangeError(100, 100));
        $this->assertNotNull(StudentExamMarksMatrix::obtainedRangeError(-100.01, 100));
        $this->assertNotNull(StudentExamMarksMatrix::obtainedRangeError(101, 100));
    }
}

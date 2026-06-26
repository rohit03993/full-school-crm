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
}

<?php

namespace Tests\Unit;

use App\Support\ExamSubjectCatalog;
use Tests\TestCase;

class ExamSubjectCatalogTest extends TestCase
{
    public function test_resolves_motion_style_subject_headers(): void
    {
        $this->assertSame('Physics', ExamSubjectCatalog::resolveLabel('P'));
        $this->assertSame('Chemistry', ExamSubjectCatalog::resolveLabel('C'));
        $this->assertSame('Mathematics', ExamSubjectCatalog::resolveLabel('M'));
        $this->assertSame('Biology', ExamSubjectCatalog::resolveLabel('Bio'));
    }

    public function test_default_max_marks_per_subject(): void
    {
        $this->assertSame(350.0, ExamSubjectCatalog::defaultMaxForHeader('P'));
        $this->assertSame(200.0, ExamSubjectCatalog::defaultMaxForHeader('C'));
        $this->assertSame(500.0, ExamSubjectCatalog::defaultMaxForHeader('M'));
    }
}

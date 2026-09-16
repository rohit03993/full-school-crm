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
        $this->assertSame('Maths', ExamSubjectCatalog::resolveLabel('M'));
        $this->assertSame('Maths', ExamSubjectCatalog::resolveLabel('Maths'));
        $this->assertSame('Maths', ExamSubjectCatalog::resolveLabel('Mathematics'));
        $this->assertSame('Biology', ExamSubjectCatalog::resolveLabel('Bio'));
        $this->assertSame('Zoology', ExamSubjectCatalog::resolveLabel('Z'));
        $this->assertSame('Physical Education', ExamSubjectCatalog::resolveLabel('PE'));
    }

    public function test_keeps_custom_subject_names(): void
    {
        $this->assertSame('Physical Education', ExamSubjectCatalog::canonicalDisplayName('Physical Education'));
    }

    public function test_default_max_marks_are_per_test_fallback(): void
    {
        $this->assertSame(100.0, ExamSubjectCatalog::defaultMaxForHeader('P'));
        $this->assertSame(180.0, ExamSubjectCatalog::defaultMaxForHeader('M', 180));
    }

    public function test_matching_stored_names_merge_maths_aliases(): void
    {
        $names = ExamSubjectCatalog::matchingStoredNames('Mathematics');

        $this->assertContains('Maths', $names);
        $this->assertContains('Mathematics', $names);
    }
}

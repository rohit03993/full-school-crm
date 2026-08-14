<?php

namespace Tests\Unit;

use App\Support\CommonCourseSubjects;
use PHPUnit\Framework\TestCase;

class CommonCourseSubjectsTest extends TestCase
{
    public function test_merge_adds_selected_presets_and_keeps_custom_rows(): void
    {
        $rows = CommonCourseSubjects::mergeIntoRows(
            ['maths', 'physics', 'english'],
            [
                ['name' => 'Geography', 'code' => 'GEO', 'default_max_marks' => 80, 'is_active' => true],
            ],
        );

        $names = array_column($rows, 'name');

        $this->assertSame(['Maths', 'Physics', 'English', 'Geography'], $names);
        $this->assertSame('MATH', $rows[0]['code']);
        $this->assertSame(100, $rows[0]['default_max_marks']);
    }

    public function test_merge_removes_unselected_presets_but_keeps_custom(): void
    {
        $rows = CommonCourseSubjects::mergeIntoRows(
            ['english'],
            [
                ['name' => 'Maths', 'code' => 'MATH', 'default_max_marks' => 100, 'is_active' => true],
                ['name' => 'English', 'code' => 'ENG', 'default_max_marks' => 100, 'is_active' => true],
                ['name' => 'Fine Arts', 'code' => 'ART', 'default_max_marks' => 50, 'is_active' => true],
            ],
        );

        $this->assertSame(['English', 'Fine Arts'], array_column($rows, 'name'));
    }

    public function test_keys_matching_rows_recognises_mathematics_as_maths(): void
    {
        $keys = CommonCourseSubjects::keysMatchingRows([
            ['name' => 'Mathematics'],
            ['name' => 'Hindi'],
        ]);

        $this->assertSame(['maths', 'hindi'], $keys);
    }

    public function test_merge_preserves_edited_code_and_max_marks_for_existing_preset(): void
    {
        $rows = CommonCourseSubjects::mergeIntoRows(
            ['physics'],
            [
                ['name' => 'Physics', 'code' => 'P1', 'default_max_marks' => 70, 'is_active' => true],
            ],
        );

        $this->assertCount(1, $rows);
        $this->assertSame('P1', $rows[0]['code']);
        $this->assertSame(70, $rows[0]['default_max_marks']);
    }

    public function test_section_merge_preserves_teacher_for_still_selected_subject(): void
    {
        $rows = CommonCourseSubjects::mergeIntoSectionRows(
            ['maths', 'physics'],
            [
                [
                    'course_subject_id' => 10,
                    'name' => 'Maths',
                    'code' => 'MATH',
                    'default_max_marks' => 100,
                    'user_id' => 25,
                ],
            ],
        );

        $this->assertSame(25, $rows[0]['user_id']);
        $this->assertSame(10, $rows[0]['course_subject_id']);
        $this->assertNull($rows[1]['user_id']);
    }
}

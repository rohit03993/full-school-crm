<?php

namespace Tests\Unit;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Support\ExamTestGroupMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExamTestGroupMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_build_groups_sessions_by_test_key(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'CLS-12',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
        $batch = Batch::query()->create([
            'name' => 'Class 12-A',
            'course_id' => $course->id,
            'trainer_user_id' => $user->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $type = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        foreach (['Mathematics', 'Physics', 'Chemistry'] as $subject) {
            ActivitySession::query()->create([
                'activity_type_id' => $type->id,
                'title' => "Unit Test — June 2026 — {$subject}",
                'session_date' => '2026-06-16',
                'batch_id' => $batch->id,
                'metadata' => [
                    'test_key' => 'unit-june',
                    'test_name' => 'Unit Test — June 2026',
                    'subject' => $subject,
                    'max_marks' => 50,
                ],
                'created_by_user_id' => $user->id,
            ]);
        }

        $matrix = ExamTestGroupMatrix::build();

        $this->assertCount(1, $matrix['rows']);
        $this->assertSame('Unit Test — June 2026', $matrix['rows'][0]['label']);
        $this->assertContains('Maths', $matrix['subjects']);
        $this->assertSame(3, count($matrix['rows'][0]['subjects']));
        $this->assertSame(['Chemistry', 'Maths', 'Physics'], $matrix['rows'][0]['subject_names']);
    }

    public function test_build_merges_maths_and_mathematics_as_one_subject(): void
    {
        $user = User::factory()->create(['is_active' => true]);
        $course = Course::query()->create([
            'name' => 'Class 11 JEE',
            'code' => 'JEE-11',
            'programme_category' => 'coaching',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);
        $batch = Batch::query()->create([
            'name' => 'Class 11-JEE A',
            'course_id' => $course->id,
            'trainer_user_id' => $user->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);
        $type = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        ActivitySession::query()->create([
            'activity_type_id' => $type->id,
            'title' => 'JEE Test — Maths',
            'session_date' => now()->toDateString(),
            'batch_id' => $batch->id,
            'metadata' => [
                'test_key' => 'jee-test',
                'test_name' => 'JEE Test',
                'subject' => 'Maths',
                'max_marks' => 100,
            ],
            'created_by_user_id' => $user->id,
        ]);
        ActivitySession::query()->create([
            'activity_type_id' => $type->id,
            'title' => 'JEE Test — Mathematics',
            'session_date' => now()->toDateString(),
            'batch_id' => $batch->id,
            'metadata' => [
                'test_key' => 'jee-test',
                'test_name' => 'JEE Test',
                'subject' => 'Mathematics',
                'max_marks' => 180,
            ],
            'created_by_user_id' => $user->id,
        ]);

        $matrix = ExamTestGroupMatrix::build();

        $this->assertCount(1, $matrix['rows']);
        $this->assertSame(['Maths'], $matrix['rows'][0]['subject_names']);
        $this->assertArrayNotHasKey('Mathematics', $matrix['rows'][0]['subjects']);
    }
}

<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\DurationType;
use App\Enums\ProgrammeCategory;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\User;
use App\Services\ClassSectionListService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClassSectionListServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_paginate_and_stats_for_session(): void
    {
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'SCH-10',
            'programme_category' => ProgrammeCategory::School,
            'duration' => 1,
            'duration_type' => DurationType::Years,
            'fee' => 80000,
            'status' => CourseStatus::Active,
        ]);

        $session = AcademicSession::query()->create([
            'name' => '2025–26',
            'code' => '2025-26',
            'starts_on' => '2025-04-01',
            'ends_on' => '2026-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $trainer = User::factory()->create(['is_active' => true]);

        Batch::query()->create([
            'name' => 'Class 10-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $trainer->id,
            'status' => BatchStatus::Active,
        ]);

        $service = app(ClassSectionListService::class);

        $this->assertSame(1, $service->paginate($session->id)->total());
        $this->assertSame([
            'sections' => 1,
            'programmes' => 1,
            'students' => 0,
        ], $service->stats($session->id));
    }
}

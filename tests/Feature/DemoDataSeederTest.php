<?php

namespace Tests\Feature;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\Student;
use App\Support\StudentExamMarksMatrix;
use Database\Seeders\DemoBaselineSeeder;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoDataSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_demo_baseline_and_demo_data_seed_successfully(): void
    {
        $this->seed(DemoBaselineSeeder::class);
        $this->seed(DemoDataSeeder::class);

        $this->assertDatabaseHas('enrollments', ['enrollment_number' => '101']);
        $this->assertDatabaseHas('enrollments', ['enrollment_number' => '102']);
        $this->assertDatabaseHas('enrollments', ['enrollment_number' => '103']);

        $student = Student::query()
            ->whereHas('activeEnrollment', fn ($query) => $query->where('enrollment_number', '103'))
            ->firstOrFail();

        $examType = ActivityType::query()->where('slug', 'exam')->firstOrFail();

        $records = app(\App\Services\ActivityAttendanceService::class)
            ->presentRecordsForStudent($student, $examType);

        $matrix = StudentExamMarksMatrix::fromRecords($records);

        $this->assertGreaterThanOrEqual(2, count($matrix['rows']));
        $this->assertContains('Mathematics', $matrix['subjects']);
        $this->assertContains('Biology', $matrix['subjects']);

        $juneSessions = ActivitySession::query()
            ->where('metadata->test_name', 'Unit Test — June 2026')
            ->count();

        $julySessions = ActivitySession::query()
            ->where('metadata->test_name', 'Unit Test — July 2026')
            ->count();

        $this->assertSame(3, $juneSessions);
        $this->assertSame(4, $julySessions);
    }
}

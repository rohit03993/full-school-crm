<?php

namespace Tests\Unit;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Services\StudentImportBatchResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentImportBatchResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_resolves_batch_by_exact_name_with_normalized_spacing(): void
    {
        [$session, $course, $batch] = $this->createBatch('12th JEE Batch C (2026-27)');

        $resolved = app(StudentImportBatchResolver::class)->resolve(
            '12th JEE Batch C (2026-27)',
            $session->id,
        );

        $this->assertNotNull($resolved);
        $this->assertSame($batch->id, $resolved->id);
    }

    public function test_resolves_batch_when_excel_spacing_differs_slightly(): void
    {
        [$session, , $batch] = $this->createBatch('11th JEE BATCH ( 2026-2027)');

        $resolved = app(StudentImportBatchResolver::class)->resolve(
            '11th JEE BATCH (2026-2027)',
            $session->id,
        );

        $this->assertNotNull($resolved);
        $this->assertSame($batch->id, $resolved->id);
    }

    /**
     * @return array{0: AcademicSession, 1: Course, 2: Batch}
     */
    protected function createBatch(string $name): array
    {
        $session = AcademicSession::query()->create([
            'name' => '2026-2027',
            'code' => '2026-2027',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'IIT JEE',
            'code' => 'JEE',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'name' => $name,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        return [$session, $course, $batch];
    }
}

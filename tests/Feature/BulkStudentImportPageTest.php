<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\RoleName;
use App\Filament\Pages\BulkStudentImportPage;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Student;
use App\Models\StudentImportBatch;
use App\Models\User;
use App\Services\StudentBulkImportService;
use App\Support\StudentImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class BulkStudentImportPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_chunked_import_continues_when_batch_status_is_processing(): void
    {
        $staff = $this->createStaffUser();
        $this->actingAs($staff);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'CLS-12-SCI',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $crmBatch = Batch::query()->create([
            'name' => '12th Science Batch A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $mapping = [
            0 => StudentImportFields::ROLL_NUMBER,
            1 => StudentImportFields::NAME,
            2 => StudentImportFields::MOBILE,
            3 => StudentImportFields::BATCH_SECTION,
        ];

        $rows = [];

        for ($index = 1; $index <= 25; $index++) {
            $rows[] = [
                (string) (100 + $index),
                "Student {$index}",
                '98765'.str_pad((string) $index, 5, '0', STR_PAD_LEFT),
                $crmBatch->name,
            ];
        }

        $preview = app(StudentBulkImportService::class)->buildPreview($mapping, $rows, $session->id);

        $previewBatch = app(StudentBulkImportService::class)->storePreviewBatch(
            $staff,
            $session->id,
            'students.xlsx',
            $preview,
        );

        $firstChunk = app(StudentBulkImportService::class)->importChunk(
            $staff,
            $previewBatch->fresh(),
            $preview,
            [],
            0,
        );

        $this->assertFalse($firstChunk['done']);
        $this->assertSame(20, $firstChunk['processed']);
        $this->assertSame('processing', $previewBatch->fresh()->status);

        Livewire::test(BulkStudentImportPage::class)
            ->set('step', 3)
            ->set('academicSessionId', $session->id)
            ->set('importBatchId', $previewBatch->id)
            ->set('importTotal', 25)
            ->set('importProcessed', 20)
            ->set('isImporting', true)
            ->set('importRunningTotals', [
                'created' => 20,
                'updated' => 0,
                'skipped' => 0,
                'failed' => 0,
                'preview_rejected' => 0,
                'errors' => [],
            ])
            ->call('runImport')
            ->assertSet('step', 4);

        $this->assertSame(25, Student::query()->count());
        $this->assertSame('completed', $previewBatch->fresh()->status);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}

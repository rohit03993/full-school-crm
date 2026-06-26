<?php

namespace Tests\Feature;

use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\RoleName;
use App\Enums\StudentImportDuplicateResolution;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentBulkImportService;
use App\Services\StudentImportColumnMapper;
use App\Support\StudentImportFields;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentBulkImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_imports_rows_with_missing_or_invalid_mobile_as_ready_with_warnings(): void
    {
        [, , $batch] = $this->seedImportBatch();

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['501', 'No Mobile Student', '', $batch->name],
                ['502', 'Bad Mobile Student', 'not-a-phone', $batch->name],
                ['503', 'Scientific Mobile', '9.18321E+11', $batch->name],
            ],
            $batch->academic_session_id,
        );

        $this->assertSame('ready', $preview[0]['status']);
        $this->assertNotEmpty($preview[0]['warnings']);
        $this->assertSame('', $preview[0]['data']['mobile']);

        $this->assertSame('ready', $preview[1]['status']);
        $this->assertNotEmpty($preview[1]['warnings']);

        $this->assertSame('ready', $preview[2]['status']);
        $this->assertStringContainsString('scientific', strtolower(implode(' ', $preview[2]['warnings'])));
    }

    public function test_import_creates_student_without_mobile_when_spreadsheet_mobile_is_invalid(): void
    {
        $staff = $this->createStaffUser();
        [, , $batch] = $this->seedImportBatch();

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['601', 'Imported Without Mobile', '9.18321E+11', $batch->name],
            ],
            $batch->academic_session_id,
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.xlsx',
            $preview,
            [],
            null,
            $batch->academic_session_id,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['without_mobile']);

        $student = Student::query()->where('name', 'Imported Without Mobile')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->mobile);
        $this->assertStringContainsString('scientific', strtolower((string) $student->mobile_import_note));
        $this->assertSame('601', $student->activeEnrollment->enrollment_number);
    }

    public function test_preview_treats_duplicate_mobile_in_file_as_ready_without_mobile(): void
    {
        [, , $batch] = $this->seedImportBatch();

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['701', 'First Duplicate', '9876500701', $batch->name],
                ['702', 'Second Duplicate', '9876500701', $batch->name],
            ],
            $batch->academic_session_id,
        );

        $this->assertSame('ready', $preview[0]['status']);
        $this->assertSame('ready', $preview[1]['status']);
        $this->assertSame('', $preview[0]['data']['mobile']);
        $this->assertSame('', $preview[1]['data']['mobile']);
        $this->assertStringContainsString('Duplicate mobile in file', implode(' ', $preview[0]['warnings']));
        $this->assertStringContainsString('row 3', implode(' ', $preview[0]['warnings']));
        $this->assertStringContainsString('row 2', implode(' ', $preview[1]['warnings']));
    }

    public function test_import_creates_both_students_without_mobile_when_file_has_duplicate_mobile(): void
    {
        $staff = $this->createStaffUser();
        [, , $batch] = $this->seedImportBatch();

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['801', 'Dup Student A', '9876500801', $batch->name],
                ['802', 'Dup Student B', '9876500801', $batch->name],
            ],
            $batch->academic_session_id,
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.xlsx',
            $preview,
            [],
            null,
            $batch->academic_session_id,
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame(2, $result['without_mobile']);

        $first = Student::query()->where('name', 'Dup Student A')->first();
        $second = Student::query()->where('name', 'Dup Student B')->first();

        $this->assertNotNull($first);
        $this->assertNotNull($second);
        $this->assertNull($first->mobile);
        $this->assertNull($second->mobile);
        $this->assertStringContainsString('Duplicate mobile in file', (string) $first->mobile_import_note);
        $this->assertStringContainsString('Duplicate mobile in file', (string) $second->mobile_import_note);
    }

    public function test_preview_normalizes_mixed_mobile_formats(): void
    {
        [, , $batch] = $this->seedImportBatch();

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['1', 'Ten Digit', '8410054825', $batch->name],
                ['2', 'With Ninety One', '919027620525', $batch->name],
                ['3', 'Excel Float', 919027620525.0, $batch->name],
            ],
            $batch->academic_session_id,
        );

        $this->assertSame('ready', $preview[0]['status']);
        $this->assertSame('8410054825', $preview[0]['data']['mobile']);
        $this->assertSame('ready', $preview[1]['status']);
        $this->assertSame('9027620525', $preview[1]['data']['mobile']);
        $this->assertSame('ready', $preview[2]['status']);
        $this->assertSame('9027620525', $preview[2]['data']['mobile']);
    }

    public function test_column_mapper_guesses_common_headers(): void
    {
        $mapping = app(StudentImportColumnMapper::class)->guess([
            'Roll No',
            'Student Name',
            'Father Name',
            'Mobile Number',
        ]);

        $this->assertSame(StudentImportFields::ROLL_NUMBER, $mapping[0]);
        $this->assertSame(StudentImportFields::NAME, $mapping[1]);
        $this->assertSame(StudentImportFields::FATHER_NAME, $mapping[2]);
        $this->assertSame(StudentImportFields::MOBILE, $mapping[3]);
    }

    public function test_column_mapper_guesses_class_course_as_batch(): void
    {
        $mapping = app(StudentImportColumnMapper::class)->guess([
            'Roll No',
            'Student Name',
            'Class (Course)',
        ]);

        $this->assertSame(StudentImportFields::BATCH_SECTION, $mapping[2]);
    }

    public function test_import_creates_enrolled_student_with_roll_number_and_fee(): void
    {
        $staff = $this->createStaffUser();
        [$session, , $batch] = $this->seedImportBatch('Class 12 Science Batch');

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::FATHER_NAME,
                3 => StudentImportFields::MOBILE,
                4 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['101', 'Import Student', 'Import Parent', '9876500101', $batch->name],
            ],
            $session->id,
        );

        $this->assertSame('ready', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.xlsx',
            $preview,
            [],
            null,
            $session->id,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(0, $result['preview_rejected']);
        $student = Student::query()->where('mobile', '9876500101')->first();
        $this->assertNotNull($student);
        $this->assertSame(StudentStatus::Enrolled, $student->status);
        $this->assertSame('101', $student->activeEnrollment->enrollment_number);
        $this->assertSame($session->id, $student->activeEnrollment->academic_session_id);
        $this->assertSame(50000.0, (float) $student->activeEnrollment->feeStructure->net_fee);
    }

    public function test_duplicate_mobile_can_be_skipped(): void
    {
        $staff = $this->createStaffUser();
        [$session, , $batch] = $this->seedImportBatch('Diploma Batch', 30000);

        Student::query()->create([
            'name' => 'Existing Student',
            'father_name' => 'Existing Parent',
            'mobile' => '9876500202',
            'status' => StudentStatus::Enquiry,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::FATHER_NAME,
                3 => StudentImportFields::MOBILE,
                4 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['202', 'New Name', 'New Parent', '9876500202', $batch->name],
            ],
            $session->id,
        );

        $this->assertSame('duplicate', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.xlsx',
            $preview,
            [2 => StudentImportDuplicateResolution::KeepExisting->value],
            null,
            $session->id,
        );

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_import_rejects_course_with_no_fee(): void
    {
        $staff = $this->createStaffUser();
        [$session, , $batch] = $this->seedImportBatch('Free Course Batch', 0);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['404', 'Zero Fee Student', '9876500404', $batch->name],
            ],
            $session->id,
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.csv',
            $preview,
            [],
            null,
            $session->id,
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertNull(Student::query()->where('mobile', '9876500404')->first());
    }

    public function test_import_proceeds_without_father_name_or_valid_dob(): void
    {
        $staff = $this->createStaffUser();
        [$session, , $batch] = $this->seedImportBatch('Class 11 Batch', 40000);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::DATE_OF_BIRTH,
                4 => StudentImportFields::GENDER,
                5 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['303', 'Minimal Student', '9876500303', 'not-a-date', 'unknown', $batch->name],
            ],
            $session->id,
        );

        $this->assertSame('ready', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.csv',
            $preview,
            [],
            null,
            $session->id,
        );

        $this->assertSame(1, $result['created']);
        $student = Student::query()->where('mobile', '9876500303')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->father_name);
        $this->assertNull($student->date_of_birth);
        $this->assertNull($student->gender);
    }

    public function test_preview_errors_are_not_counted_as_import_failures(): void
    {
        $staff = $this->createStaffUser();
        [$session, , $batch] = $this->seedImportBatch('Class 10 Batch', 25000);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['401', 'Valid Student', '9876500401', $batch->name],
                ['', 'Missing Roll', '9876500402', $batch->name],
            ],
            $session->id,
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.csv',
            $preview,
            [],
            null,
            $session->id,
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['preview_rejected']);
        $this->assertSame(0, $result['failed']);
    }

    public function test_import_assigns_course_from_each_matched_batch_in_one_file(): void
    {
        $staff = $this->createStaffUser();
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $jeeCourse = Course::query()->create([
            'name' => 'IIT JEE',
            'code' => 'JEE',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $neetCourse = Course::query()->create([
            'name' => 'NEET',
            'code' => 'NEET',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 45000,
            'status' => CourseStatus::Active,
        ]);

        $jeeBatch = Batch::query()->create([
            'name' => '12th JEE Batch A (2026-27)',
            'course_id' => $jeeCourse->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $neetBatch = Batch::query()->create([
            'name' => '11th NEET BATCH (2026-2027)',
            'course_id' => $neetCourse->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::BATCH_SECTION,
            ],
            [
                ['701', 'JEE Student', '9876500701', $jeeBatch->name],
                ['702', 'NEET Student', '9876500702', $neetBatch->name],
            ],
            $session->id,
        );

        $this->assertSame('ready', $preview[0]['status']);
        $this->assertSame('IIT JEE', $preview[0]['resolved_batch']['course_name']);
        $this->assertSame('NEET', $preview[1]['resolved_batch']['course_name']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            'students.xlsx',
            $preview,
            [],
            null,
            $session->id,
        );

        $this->assertSame(2, $result['created']);
        $this->assertSame($jeeCourse->id, Student::query()->where('name', 'JEE Student')->first()?->activeEnrollment?->course_id);
        $this->assertSame($neetCourse->id, Student::query()->where('name', 'NEET Student')->first()?->activeEnrollment?->course_id);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    /**
     * @return array{0: AcademicSession, 1: Course, 2: Batch}
     */
    protected function seedImportBatch(string $batchName = 'Import Batch', float $fee = 50000): array
    {
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Import Course',
            'code' => 'IMP-01',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => $fee,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'name' => $batchName,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        return [$session, $course, $batch];
    }
}

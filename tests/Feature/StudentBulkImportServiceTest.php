<?php

namespace Tests\Feature;

use App\Enums\CourseStatus;
use App\Enums\RoleName;
use App\Enums\StudentImportDuplicateResolution;
use App\Enums\StudentStatus;
use App\Models\AcademicSession;
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
        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
            ],
            [
                ['501', 'No Mobile Student', ''],
                ['502', 'Bad Mobile Student', 'not-a-phone'],
                ['503', 'Scientific Mobile', '9.18321E+11'],
            ],
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

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
            ],
            [
                ['601', 'Imported Without Mobile', '9.18321E+11'],
            ],
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.xlsx',
            $preview,
            [],
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['without_mobile']);

        $student = Student::query()->where('name', 'Imported Without Mobile')->first();
        $this->assertNotNull($student);
        $this->assertNull($student->mobile);
        $this->assertSame('601', $student->activeEnrollment->enrollment_number);
    }

    public function test_preview_normalizes_mixed_mobile_formats(): void
    {
        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
            ],
            [
                ['1', 'Ten Digit', '8410054825'],
                ['2', 'With Ninety One', '919027620525'],
                ['3', 'Excel Float', 919027620525.0],
            ],
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

    public function test_import_creates_enrolled_student_with_roll_number_and_fee(): void
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
        $course = Course::query()->create([
            'name' => 'Class 12 Science',
            'code' => 'CLS-12-SCI',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::FATHER_NAME,
                3 => StudentImportFields::MOBILE,
            ],
            [
                ['101', 'Import Student', 'Import Parent', '9876500101'],
            ],
        );

        $this->assertSame('ready', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.xlsx',
            $preview,
            [],
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
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => 'Diploma',
            'code' => 'DIP-01',
            'programme_category' => 'coaching',
            'duration' => 6,
            'duration_type' => 'months',
            'fee' => 30000,
            'status' => CourseStatus::Active,
        ]);

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
            ],
            [
                ['202', 'New Name', 'New Parent', '9876500202'],
            ],
        );

        $this->assertSame('duplicate', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.xlsx',
            $preview,
            [2 => StudentImportDuplicateResolution::KeepExisting->value],
        );

        $this->assertSame(1, $result['skipped']);
        $this->assertSame(0, Enrollment::query()->count());
    }

    public function test_import_rejects_course_with_no_fee(): void
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
        $course = Course::query()->create([
            'name' => 'Free Course',
            'code' => 'FREE-01',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 0,
            'status' => CourseStatus::Active,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
            ],
            [
                ['404', 'Zero Fee Student', '9876500404'],
            ],
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.csv',
            $preview,
            [],
        );

        $this->assertSame(0, $result['created']);
        $this->assertSame(1, $result['failed']);
        $this->assertNull(Student::query()->where('mobile', '9876500404')->first());
    }

    public function test_import_proceeds_without_father_name_or_valid_dob(): void
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
        $course = Course::query()->create([
            'name' => 'Class 11 Science',
            'code' => 'CLS-11-SCI',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
                3 => StudentImportFields::DATE_OF_BIRTH,
                4 => StudentImportFields::GENDER,
            ],
            [
                ['303', 'Minimal Student', '9876500303', 'not-a-date', 'unknown'],
            ],
        );

        $this->assertSame('ready', $preview[0]['status']);

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.csv',
            $preview,
            [],
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
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);
        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'CLS-10',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 25000,
            'status' => CourseStatus::Active,
        ]);

        $preview = app(StudentBulkImportService::class)->buildPreview(
            [
                0 => StudentImportFields::ROLL_NUMBER,
                1 => StudentImportFields::NAME,
                2 => StudentImportFields::MOBILE,
            ],
            [
                ['401', 'Valid Student', '9876500401'],
                ['', 'Missing Roll', '9876500402'],
            ],
        );

        $result = app(StudentBulkImportService::class)->import(
            $staff,
            $session,
            $course,
            null,
            'students.csv',
            $preview,
            [],
        );

        $this->assertSame(1, $result['created']);
        $this->assertSame(1, $result['preview_rejected']);
        $this->assertSame(0, $result['failed']);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }
}

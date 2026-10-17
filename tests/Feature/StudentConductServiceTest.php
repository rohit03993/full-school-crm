<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Filament\Pages\ManageConductTypes;
use App\Models\Admission;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use App\Services\EnquiryService;
use App\Services\StudentActivityTimelineService;
use App\Services\StudentConductService;
use App\Support\ConductTypeOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentConductServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_academic_coordinator_saves_several_types_with_a_fine(): void
    {
        $coordinator = $this->coordinator();
        $student = $this->enrolledStudent($coordinator);

        $record = app(StudentConductService::class)->store($student, $coordinator, [
            'recorded_on' => now()->toDateString(),
            'type_keys' => ['creating_noise', 'phone_in_classroom'],
            'fine_choice' => 'fine_charged',
            'amount' => '200',
            'note' => 'Talking loudly and using a phone in class.',
        ]);

        $this->assertTrue($record->fine_charged);
        $this->assertSame(200, $record->amount);
        $this->assertSame(['creating_noise', 'phone_in_classroom'], $record->type_keys);
        $this->assertSame('Creating noise, Using phone in classroom', $record->typeLabelList());
        $this->assertSame('Fine charged · ₹200', $record->fineSummary());

        $timeline = app(StudentActivityTimelineService::class)->forStudent($student, $coordinator);
        $item = collect($timeline['items'])->firstWhere('type', 'conduct');
        $this->assertNotNull($item);
        $this->assertSame('conduct', $item['tab']);
    }

    public function test_without_fine_stores_no_amount(): void
    {
        $coordinator = $this->coordinator();
        $student = $this->enrolledStudent($coordinator);

        $record = app(StudentConductService::class)->store($student, $coordinator, [
            'recorded_on' => now()->toDateString(),
            'type_keys' => ['indiscipline'],
            'fine_choice' => 'without_fine',
            'amount' => '500',
            'note' => 'Warned in class. No money taken.',
        ]);

        $this->assertFalse($record->fine_charged);
        $this->assertNull($record->amount);
        $this->assertSame('Without fine', $record->fineSummary());
    }

    public function test_fine_charged_requires_a_whole_amount(): void
    {
        $coordinator = $this->coordinator();
        $student = $this->enrolledStudent($coordinator);

        try {
            app(StudentConductService::class)->store($student, $coordinator, [
                'recorded_on' => now()->toDateString(),
                'type_keys' => ['fighting'],
                'fine_choice' => 'fine_charged',
                'amount' => '',
                'note' => 'Fight in the corridor.',
            ]);
            $this->fail('A fine without an amount should not save.');
        } catch (ValidationException $exception) {
            $this->assertSame('Enter the fine amount.', $exception->errors()['amount'][0]);
        }
    }

    public function test_teacher_cannot_add_a_conduct_record(): void
    {
        $coordinator = $this->coordinator();
        $teacher = User::factory()->create(['is_active' => true]);
        $teacher->assignRole(StaffJobRole::Teacher->value);
        $student = $this->enrolledStudent($coordinator);

        $this->expectException(ValidationException::class);

        app(StudentConductService::class)->store($student, $teacher, [
            'recorded_on' => now()->toDateString(),
            'type_keys' => ['creating_noise'],
            'fine_choice' => 'without_fine',
            'note' => 'Noise.',
        ]);
    }

    public function test_only_super_admin_can_remove_a_record(): void
    {
        $coordinator = $this->coordinator();
        $student = $this->enrolledStudent($coordinator);
        $record = app(StudentConductService::class)->store($student, $coordinator, [
            'recorded_on' => now()->toDateString(),
            'type_keys' => ['fighting'],
            'fine_choice' => 'without_fine',
            'note' => 'Stopped a fight.',
        ]);

        try {
            app(StudentConductService::class)->delete($record, $coordinator);
            $this->fail('Academic coordinator should not remove a record.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only Super Admin can remove a conduct record.', $exception->errors()['conduct'][0]);
        }

        $admin = $this->superAdmin();
        app(StudentConductService::class)->delete($record, $admin);

        $this->assertDatabaseMissing('student_conduct_records', ['id' => $record->id]);
    }

    public function test_hidden_type_cannot_be_selected(): void
    {
        $types = ConductTypeOptions::all();
        foreach ($types as &$type) {
            if ($type['value'] === 'fighting') {
                $type['is_active'] = false;
            }
        }
        unset($type);
        ConductTypeOptions::save($types);

        $coordinator = $this->coordinator();
        $student = $this->enrolledStudent($coordinator);

        $this->expectException(ValidationException::class);

        app(StudentConductService::class)->store($student, $coordinator, [
            'recorded_on' => now()->toDateString(),
            'type_keys' => ['fighting'],
            'fine_choice' => 'without_fine',
            'note' => 'Should not save.',
        ]);
    }

    public function test_conduct_is_open_for_admin_and_academic_head_without_a_license_switch(): void
    {
        $coordinator = $this->coordinator();
        $this->actingAs($coordinator);
        $this->assertTrue(app(StudentConductService::class)->canRecord($coordinator));
        $this->assertFalse(ManageConductTypes::canAccess());

        $admin = $this->superAdmin();
        $this->actingAs($admin);
        $this->assertTrue(app(StudentConductService::class)->canRecord($admin));
        $this->assertTrue(ManageConductTypes::canAccess());
    }

    protected function coordinator(): User
    {
        $user = User::factory()->create([
            'name' => 'Academic Coordinator',
            'is_active' => true,
        ]);
        $user->assignRole(StaffJobRole::AcademicCoordinator->value);

        return $user;
    }

    protected function superAdmin(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create([
            'name' => 'Super Admin',
            'is_active' => true,
        ]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function enrolledStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 9',
            'code' => 'C9-CONDUCT-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Conduct Student',
            'mobile' => '98'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
            'visit_status' => VisitStatus::Interested->value,
        ], $staff, LeadSource::WalkIn);

        $student = $enquiry->student;
        $student->update([
            'father_name' => 'Father Name',
            'date_of_birth' => '2012-01-10',
            'status' => StudentStatus::Enrolled,
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'ADM-CONDUCT-'.$student->id.'-'.uniqid(),
            'course_fee' => 40000,
            'discount_amount' => 0,
            'net_fee' => 40000,
            'use_installment_plan' => false,
            'status' => AdmissionStatus::Approved,
            'approved_at' => now(),
            'submitted_at' => now(),
        ]);

        Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'enrollment_number' => 'ENR-CONDUCT-'.$student->id.'-'.uniqid(),
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        return $student->fresh(['activeEnrollment']);
    }
}

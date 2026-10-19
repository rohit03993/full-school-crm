<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StaffJobRole;
use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Enrollment;
use App\Models\HomeworkAssignment;
use App\Models\Student;
use App\Models\User;
use App\Services\BatchStaffAssignmentService;
use App\Services\EnquiryService;
use App\Services\HomeworkAssignmentService;
use App\Services\StudentCounterService;
use App\Services\StudentDropService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class StudentDropServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_coordinator_drops_an_enrolled_student_and_keeps_old_records(): void
    {
        $coordinator = $this->coordinator();
        $admin = $this->superAdmin();
        $teacher = $this->teacher();
        [$student, $batch] = $this->enrolledStudentInClass($coordinator, $teacher);

        $this->assertTrue(app(StudentDropService::class)->canDrop($coordinator));
        $this->assertTrue(app(StudentDropService::class)->canDrop($admin));
        $this->assertFalse(app(StudentDropService::class)->canDrop($teacher));
        $this->assertTrue(app(BatchStaffAssignmentService::class)->canViewStudent($teacher, $student));

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => '2026-10-15',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $coordinator->id,
        ]);

        $homework = new HomeworkAssignment(['batch_id' => $batch->id]);
        $this->assertTrue(app(HomeworkAssignmentService::class)->studentCanAccess($homework, $student));

        $drop = app(StudentDropService::class)->drop($student, $coordinator, 'Family shifted to another city.');

        $student->refresh();
        $enrollment = $student->activeEnrollment;
        $seat = BatchStudent::query()->where('student_id', $student->id)->first();

        $this->assertSame(StudentStatus::Dropped, $student->status);
        $this->assertNotNull($enrollment);
        $this->assertTrue($enrollment->is_active);
        $this->assertSame(EnrollmentStatus::Dropped, $enrollment->status);
        $this->assertNotNull($seat);
        $this->assertFalse($seat->is_active);
        $this->assertSame($batch->id, $seat->batch_id);
        $this->assertSame($batch->id, $drop->batch_id);
        $this->assertSame('Family shifted to another city.', $drop->reason);
        $this->assertSame($coordinator->id, $drop->dropped_by_user_id);
        $this->assertSame(1, Attendance::query()->where('student_id', $student->id)->count());
        $this->assertFalse(app(HomeworkAssignmentService::class)->studentCanAccess($homework, $student));
        $this->assertFalse(
            app(HomeworkAssignmentService::class)->batchStudentsWithMobile($batch->id)->contains('id', $student->id),
        );
        $this->assertFalse(app(BatchStaffAssignmentService::class)->canViewStudent($teacher, $student));
        $this->assertTrue(app(BatchStaffAssignmentService::class)->canViewStudent($coordinator, $student));

        $profile = app(StudentCounterService::class)->profile($student->fresh());
        $this->assertNotNull($profile['dossier']);
        $this->assertSame($batch->id, $profile['dossier']['batch']?->id);
        $this->assertSame('Family shifted to another city.', $profile['dossier']['drop']?->reason);

        $summary = app(\App\Services\AttendanceService::class)->summaryForStudentInRange(
            $student->fresh(),
            Carbon::parse('2026-10-01'),
            Carbon::parse('2026-10-17'),
            false,
        );
        $this->assertNotNull($summary);
        $this->assertSame(1, $summary['present_days']);

        try {
            app(StudentDropService::class)->drop($student->fresh(), $coordinator, 'Again.');
            $this->fail('A dropped student cannot be dropped a second time.');
        } catch (ValidationException $exception) {
            $this->assertSame('This student is already inactive.', $exception->errors()['reason'][0]);
        }
    }

    public function test_teacher_cannot_drop_a_student(): void
    {
        $coordinator = $this->coordinator();
        $teacher = $this->teacher();
        [$student] = $this->enrolledStudentInClass($coordinator, $teacher);

        try {
            app(StudentDropService::class)->drop($student, $teacher, 'Not allowed.');
            $this->fail('A teacher must not drop a student.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Only Super Admin or Academic coordinator can mark a student inactive.',
                $exception->errors()['reason'][0],
            );
        }

        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
    }

    public function test_reason_is_required(): void
    {
        $coordinator = $this->coordinator();
        $teacher = $this->teacher();
        [$student] = $this->enrolledStudentInClass($coordinator, $teacher);

        try {
            app(StudentDropService::class)->drop($student, $coordinator, '   ');
            $this->fail('A blank reason must not save.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Enter the reason for marking this student inactive.',
                $exception->errors()['reason'][0],
            );
        }

        $this->assertSame(StudentStatus::Enrolled, $student->fresh()->status);
        $this->assertTrue($student->fresh()->activeBatchStudent->is_active);
    }

    public function test_a_lead_cannot_be_dropped(): void
    {
        $coordinator = $this->coordinator();
        $student = Student::query()->create([
            'name' => 'Lead Only',
            'mobile' => '9811111111',
            'status' => StudentStatus::Enquiry,
        ]);

        try {
            app(StudentDropService::class)->drop($student, $coordinator, 'Not enrolled yet.');
            $this->fail('A lead must not be dropped.');
        } catch (ValidationException $exception) {
            $this->assertSame('Only an enrolled student can be marked inactive.', $exception->errors()['reason'][0]);
        }
    }

    public function test_admin_can_make_an_inactive_student_active_again(): void
    {
        $coordinator = $this->coordinator();
        $teacher = $this->teacher();
        [$student, $batch] = $this->enrolledStudentInClass($coordinator, $teacher);
        $homework = new HomeworkAssignment(['batch_id' => $batch->id]);
        $service = app(StudentDropService::class);

        $service->drop($student, $coordinator, 'Will join later.');

        $this->assertFalse(app(BatchStaffAssignmentService::class)->canViewStudent($teacher, $student->fresh()));
        $this->assertFalse($service->canDrop($teacher));

        try {
            $service->reinstate($student->fresh(), $teacher);
            $this->fail('A teacher must not make a student active again.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Only Super Admin or Academic coordinator can make a student active again.',
                $exception->errors()['reason'][0],
            );
        }

        $active = $service->reinstate($student->fresh(), $coordinator);

        $this->assertSame(StudentStatus::Enrolled, $active->status);
        $this->assertSame(EnrollmentStatus::Enrolled, $active->activeEnrollment->status);
        $this->assertTrue($active->activeEnrollment->is_active);
        $this->assertTrue($active->activeBatchStudent->is_active);
        $this->assertSame($batch->id, $active->activeBatchStudent->batch_id);
        $this->assertTrue(app(HomeworkAssignmentService::class)->studentCanAccess($homework, $active));
        $this->assertTrue(app(BatchStaffAssignmentService::class)->canViewStudent($teacher, $active));
        $this->assertSame(1, \App\Models\StudentDrop::query()->where('student_id', $student->id)->count());
        $dropRow = \App\Models\StudentDrop::query()->where('student_id', $student->id)->first();
        $this->assertNotNull($dropRow->reactivated_at);
        $this->assertSame($coordinator->id, $dropRow->reactivated_by_user_id);

        try {
            $service->reinstate($active->fresh(), $coordinator);
            $this->fail('An active student cannot be made active again.');
        } catch (ValidationException $exception) {
            $this->assertSame(
                'Only an inactive student can be made active again.',
                $exception->errors()['reason'][0],
            );
        }
    }

    public function test_inactive_date_is_saved_and_locks_records_until_the_student_is_active(): void
    {
        Carbon::setTestNow('2026-10-19 18:00:00');

        try {
            $coordinator = $this->coordinator();
            $teacher = $this->teacher();
            [$student, $batch] = $this->enrolledStudentInClass($coordinator, $teacher);
            $student->activeEnrollment->update(['enrolled_at' => '2026-04-01 09:00:00']);

            $past = Attendance::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'attendance_date' => '2026-09-15',
                'status' => AttendanceStatus::Present,
                'marked_by_user_id' => $coordinator->id,
            ]);

            $service = app(StudentDropService::class);

            try {
                $service->drop($student, $coordinator, 'In the future.', '2026-12-01 10:00:00');
                $this->fail('A future inactive date must be refused.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Inactive from cannot be a future date or time.',
                    $exception->errors()['inactive_at'][0],
                );
            }

            try {
                $service->drop($student->fresh(), $coordinator, 'Too soon.', '2020-01-01 10:00:00');
                $this->fail('A date before the student joined must be refused.');
            } catch (ValidationException $exception) {
                $this->assertSame(
                    'Inactive from cannot be before the student joined.',
                    $exception->errors()['inactive_at'][0],
                );
            }

            $service->drop($student->fresh(), $coordinator, 'Left early.', '2026-10-01 10:30:00');
            $student->refresh();
            $this->assertSame(StudentStatus::Dropped, $student->status);

            $drop = $student->drops()->first();
            $this->assertSame('2026-10-01 10:30:00', $drop->dropped_at->timezone(config('app.timezone'))->format('Y-m-d H:i:s'));

            try {
                $past->delete();
                $this->fail('Attendance cannot be deleted while the student is inactive.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('inactive', $exception->errors()['student'][0]);
            }

            try {
                Attendance::query()->create([
                    'batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'attendance_date' => '2026-09-01',
                    'status' => AttendanceStatus::Present,
                    'marked_by_user_id' => $coordinator->id,
                ]);
                $this->fail('No attendance can be added while the student is inactive.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('inactive', $exception->errors()['student'][0]);
            }

            $this->assertSame(1, Attendance::query()->where('student_id', $student->id)->count());

            $service->reinstate($student->fresh(), $coordinator);

            Attendance::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'attendance_date' => '2026-10-19',
                'status' => AttendanceStatus::Present,
                'marked_by_user_id' => $coordinator->id,
            ]);

            try {
                Attendance::query()->create([
                    'batch_id' => $batch->id,
                    'student_id' => $student->id,
                    'attendance_date' => '2026-10-05',
                    'status' => AttendanceStatus::Absent,
                    'marked_by_user_id' => $coordinator->id,
                ]);
                $this->fail('A date inside the inactive period must stay closed.');
            } catch (ValidationException $exception) {
                $this->assertStringContainsString('inactive period', $exception->errors()['student'][0]);
            }

            $titles = collect(app(\App\Services\StudentActivityTimelineService::class)->forStudent($student->fresh(), $coordinator)['items'])
                ->pluck('title');

            $this->assertTrue($titles->contains('Marked inactive'));
            $this->assertTrue($titles->contains('Made active'));
        } finally {
            Carbon::setTestNow();
        }
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

    protected function teacher(): User
    {
        $user = User::factory()->create([
            'name' => 'Class Teacher',
            'is_active' => true,
        ]);
        $user->assignRole(StaffJobRole::Teacher->value);

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

    /**
     * @return array{0: Student, 1: Batch}
     */
    protected function enrolledStudentInClass(User $staff, User $teacher): array
    {
        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27-drop-'.uniqid(),
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 9',
            'code' => 'C9-DROP-'.uniqid(),
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 40000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Drop Student',
            'mobile' => '97'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT),
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
            'admission_number' => 'ADM-DROP-'.$student->id.'-'.uniqid(),
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
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ENR-DROP-'.$student->id.'-'.uniqid(),
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => '9-A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'trainer_user_id' => $teacher->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'assigned_at' => '2026-04-01 09:00:00',
            'is_active' => true,
            'assigned_by_user_id' => $staff->id,
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $teacher->id,
            'role' => BatchStaffRole::LeadTeacher,
        ]);

        return [$student->fresh(['activeEnrollment', 'activeBatchStudent']), $batch];
    }
}

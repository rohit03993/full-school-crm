<?php

namespace Tests\Feature;

use App\Enums\CallStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\VisitStatus;
use App\Models\Course;
use App\Models\Enquiry;
use App\Models\Student;
use App\Models\User;
use App\Services\CallLogService;
use App\Services\EnquiryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class CallLogServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_connected_call_is_logged_with_staff_name_and_visit(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);

        $call = app(CallLogService::class)->log($student, $staff, [
            'call_connected' => true,
            'call_direction' => 'outgoing',
            'who_answered' => 'father',
            'visit_status' => VisitStatus::Interested->value,
            'call_notes' => 'Interested in Class 12 admission next month.',
            'next_followup_at' => now()->addDays(2)->format('Y-m-d H:i:s'),
        ]);

        $student->refresh();

        $this->assertSame($staff->id, $call->user_id);
        $this->assertSame(CallStatus::Connected, $call->call_status);
        $this->assertSame(1, $student->total_calls);
        $this->assertNotNull($student->last_call_at);
        $this->assertDatabaseHas('visits', [
            'student_id' => $student->id,
            'staff_user_id' => $staff->id,
        ]);
    }

    public function test_three_not_connected_calls_block_student(): void
    {
        $staff = $this->createStaffUser();
        $student = $this->createLeadStudent($staff);
        $service = app(CallLogService::class);

        foreach (['no_answer', 'busy', 'switched_off'] as $status) {
            $service->log($student->fresh(), $staff, [
                'call_connected' => false,
                'call_status' => $status,
            ]);
        }

        $student->refresh();

        $this->assertTrue($student->is_call_blocked);
        $this->assertSame(3, $student->total_calls);
        $this->assertFalse($student->isCallable());
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::Staff->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true, 'name' => 'Telecaller One']);
        $user->assignRole(RoleName::Staff->value);

        return $user;
    }

    protected function createLeadStudent(User $staff): Student
    {
        $course = Course::query()->create([
            'name' => 'Class 12 Commerce',
            'code' => 'C12-CALL',
            'programme_category' => 'school',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 120000,
            'status' => CourseStatus::Active,
        ]);

        $enquiry = app(EnquiryService::class)->create([
            'name' => 'Call Test Student',
            'mobile' => '9811223344',
            'course_id' => $course->id,
            'meeting_with_user_id' => $staff->id,
        ], $staff, LeadSource::WalkIn);

        return $enquiry->student;
    }
}

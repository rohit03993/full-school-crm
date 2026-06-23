<?php

namespace Tests\Feature;

use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\Gender;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\AttendanceWhatsAppService;
use App\Services\WhatsAppTemplateParamResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AttendanceWhatsAppServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_queues_whatsapp_for_present_students_when_enabled(): void
    {
        $this->travelTo('2026-06-20 17:32:00');

        $staff = $this->createStaffUser();
        $batch = $this->createBatch();
        $present = $this->createStudentInBatch($batch, '9000000001');
        $absent = $this->createStudentInBatch($batch, '9000000002');
        $presentNoMobile = $this->createStudentInBatch($batch, '');

        $template = WhatsAppTemplate::query()->create([
            'name' => 'parent_attendance_auto_in_agra',
            'param_count' => 4,
            'param_mappings' => [
                'student.name',
                'student.enrollment_number',
                'campaign.time',
                'campaign.date',
            ],
            'body' => 'Hi {{1}}, roll {{2}}, time {{3}}, date {{4}}',
            'is_active' => true,
        ]);

        Setting::setValue('whatsapp.attendance_autosend_enabled', '1', 'whatsapp');
        Setting::setValue('whatsapp.attendance_autosend_template_id', (string) $template->id, 'whatsapp');

        $marks = [
            $present->id => AttendanceStatus::Present->value,
            $absent->id => AttendanceStatus::Absent->value,
            $presentNoMobile->id => AttendanceStatus::Present->value,
        ];

        $queued = app(AttendanceWhatsAppService::class)->maybeQueueAfterBatchAttendance(
            $batch,
            '2026-06-20',
            $marks,
            $staff,
        );

        $this->assertSame(1, $queued);

        $campaign = WhatsAppCampaign::query()->first();
        $this->assertNotNull($campaign);
        $this->assertSame('attendance', $campaign->campaignVariable('audience_source'));
        $this->assertSame('2026-06-20', $campaign->campaignVariable('date'));
        $this->assertSame('17:32:00', $campaign->campaignVariable('time'));
        $this->assertSame($batch->id, $campaign->batch_id);
        $this->assertCount(1, $campaign->recipients);

        $params = app(WhatsAppTemplateParamResolver::class)->resolveAll(
            $template->paramSources(),
            $present->fresh(),
            $staff,
            null,
            $campaign,
        );

        $this->assertSame($present->name, $params[0]);
        $this->assertSame('17:32:00', $params[2]);
        $this->assertSame('2026-06-20', $params[3]);
        $this->assertSame('Present', $campaign->campaignVariable('_student_attendance_status')[$present->id] ?? '');
    }

    public function test_skips_when_attendance_autosend_disabled(): void
    {
        $staff = $this->createStaffUser();
        $batch = $this->createBatch();
        $student = $this->createStudentInBatch($batch, '9000000003');

        $queued = app(AttendanceWhatsAppService::class)->maybeQueueAfterBatchAttendance(
            $batch,
            '2026-06-18',
            [$student->id => AttendanceStatus::Absent->value],
            $staff,
        );

        $this->assertNull($queued);
        $this->assertDatabaseCount('whatsapp_campaigns', 0);
    }

    protected function createStaffUser(): User
    {
        Role::query()->firstOrCreate(['name' => RoleName::SuperAdmin->value, 'guard_name' => 'web']);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }

    protected function createBatch(): Batch
    {
        $staff = User::factory()->create();

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'ATT-WA',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        return Batch::query()->create([
            'course_id' => $course->id,
            'name' => 'Batch A',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);
    }

    protected function createStudentInBatch(Batch $batch, string $mobile): Student
    {
        $student = Student::query()->create([
            'name' => 'Student '.($mobile !== '' ? $mobile : 'No Mobile'),
            'father_name' => 'Parent',
            'date_of_birth' => '2000-01-01',
            'gender' => Gender::Male,
            'mobile' => $mobile !== '' ? $mobile : '9000000098',
            'status' => StudentStatus::Enquiry,
        ]);

        if ($mobile === '') {
            Student::query()->whereKey($student->id)->update(['mobile' => '']);
            $student->refresh();
        }

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $batch->trainer_user_id,
        ]);

        return $student;
    }
}

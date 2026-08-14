<?php

namespace Tests\Feature;

use App\Enums\BatchStaffRole;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\HomeworkAssignmentStatus;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\HomeworkCheckPage;
use App\Filament\Pages\HomeworkPage;
use App\Filament\Pages\HomeworkReviewPage;
use App\Filament\Pages\SubmitHomeworkPage;
use App\Filament\Resources\HomeworkAssignments\HomeworkAssignmentResource;
use App\Models\AcademicSession;
use App\Models\Batch;
use App\Models\BatchStaffAssignment;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\HomeworkAssignment;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use App\Services\HomeworkSubmissionService;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class HomeworkSubmissionServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Role::findOrCreate(RoleName::SuperAdmin->value);
        Role::findOrCreate(RoleName::Staff->value);

        Setting::setValue('meta_whatsapp.enabled', '1', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.phone_number_id', '1234567890', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString('meta-test-token'), 'meta_whatsapp');
        Setting::flushValueCache();
    }

    public function test_sidebar_registers_only_one_homework_entry(): void
    {
        $this->assertTrue(HomeworkPage::shouldRegisterNavigation());
        $this->assertFalse(SubmitHomeworkPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkReviewPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkCheckPage::shouldRegisterNavigation());
        $this->assertFalse(HomeworkAssignmentResource::shouldRegisterNavigation());
        $this->assertFalse(HomeworkAssignmentResource::canCreate());
    }

    public function test_teacher_submit_creates_submitted_assignment(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $assignment = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra practice',
            'description' => 'Complete exercise 5.2',
        ]);

        $this->assertSame(HomeworkAssignmentStatus::Submitted, $assignment->status);
        $this->assertNull($assignment->published_at);
        $this->assertSame($data['mathTeacher']->id, $assignment->submitted_by_user_id);
        $this->assertNull($assignment->approved_by_user_id);
    }

    public function test_teacher_cannot_submit_subject_they_do_not_teach(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $this->expectException(ValidationException::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Optics',
        ]);
    }

    public function test_admin_save_is_approved_immediately(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $assignment = $service->submit($data['admin'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Chapter 3 numericals',
        ], asAdmin: true);

        $this->assertSame(HomeworkAssignmentStatus::Approved, $assignment->status);
        $this->assertNotNull($assignment->published_at);
        $this->assertNotNull($assignment->approved_at);
    }

    public function test_board_lists_every_subject_with_status(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $board = $service->boardForClassDate($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $board['summary']['total']);
        $this->assertSame(1, $board['summary']['submitted']);
        $this->assertSame(1, $board['summary']['missing']);
    }

    public function test_combined_send_only_covers_approved_subjects(): void
    {
        $sequence = 0;

        // Meta returns a unique wamid per message; meta_whatsapp_messages.wamid is unique.
        Http::fake([
            'https://graph.facebook.com/*' => function () use (&$sequence) {
                $sequence++;

                return Http::response([
                    'messages' => [['id' => 'wamid.HWC'.$sequence]],
                ], 200);
            },
        ]);

        $data = $this->seedClass();
        $this->seedCombinedTemplate();
        $service = app(HomeworkSubmissionService::class);

        // Maths submitted then approved; Physics still only submitted (should be excluded).
        $maths = $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);
        $service->approve($data['admin'], $maths->id);

        $service->submit($data['physicsTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['physics']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Physics',
            'description' => 'Optics',
        ]);

        $result = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(2, $result['sent'], (string) ($result['error'] ?? ''));
        $this->assertSame(1, $result['subjects']);

        $this->assertSame(
            HomeworkAssignmentStatus::Sent,
            HomeworkAssignment::query()->find($maths->id)->status,
        );
        $this->assertSame(
            HomeworkAssignmentStatus::Submitted,
            HomeworkAssignment::query()
                ->where('course_subject_id', $data['physics']->id)
                ->first()->status,
        );
    }

    public function test_combined_send_without_approved_returns_error(): void
    {
        $data = $this->seedClass();
        $service = app(HomeworkSubmissionService::class);

        $service->submit($data['mathTeacher'], [
            'batch_id' => $data['batch']->id,
            'course_subject_id' => $data['maths']->id,
            'homework_date' => now()->toDateString(),
            'title' => 'Algebra',
            'description' => 'Ex 5.2',
        ]);

        $result = $service->combinedSend($data['admin'], $data['batch']->id, now()->toDateString());

        $this->assertSame(0, $result['sent']);
        $this->assertNotNull($result['error']);
    }

    /**
     * @return array{
     *     admin: User,
     *     mathTeacher: User,
     *     physicsTeacher: User,
     *     batch: Batch,
     *     maths: CourseSubject,
     *     physics: CourseSubject
     * }
     */
    protected function seedClass(): array
    {
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole(RoleName::SuperAdmin->value);

        $mathTeacher = User::factory()->create(['is_active' => true]);
        $mathTeacher->assignRole(RoleName::Staff->value);

        $physicsTeacher = User::factory()->create(['is_active' => true]);
        $physicsTeacher->assignRole(RoleName::Staff->value);

        $session = AcademicSession::query()->create([
            'name' => '2026–27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 11 JEE',
            'code' => 'CLS-11-JEE',
            'programme_category' => 'coaching',
            'duration' => 1,
            'duration_type' => 'years',
            'fee' => 50000,
            'status' => CourseStatus::Active,
        ]);

        $maths = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Mathematics',
            'code' => 'MATH',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        $physics = CourseSubject::query()->create([
            'course_id' => $course->id,
            'name' => 'Physics',
            'code' => 'PHY',
            'default_max_marks' => 100,
            'sort_order' => 2,
            'is_active' => true,
        ]);

        $batch = Batch::query()->create([
            'name' => 'Class 11 JEE - A',
            'section' => 'A',
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'start_date' => '2026-04-01',
            'end_date' => '2027-03-31',
            'status' => BatchStatus::Active,
        ]);

        $batch->subjects()->attach([
            $maths->id => ['sort_order' => 1],
            $physics->id => ['sort_order' => 2],
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $mathTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $maths->id,
        ]);

        BatchStaffAssignment::query()->create([
            'batch_id' => $batch->id,
            'user_id' => $physicsTeacher->id,
            'role' => BatchStaffRole::SubjectTeacher,
            'course_subject_id' => $physics->id,
        ]);

        foreach (['Riya Sharma' => '9876500001', 'Aman Verma' => '9876500002'] as $name => $mobile) {
            $student = Student::query()->create([
                'name' => $name,
                'mobile' => $mobile,
                'status' => StudentStatus::Enrolled,
            ]);

            BatchStudent::query()->create([
                'batch_id' => $batch->id,
                'student_id' => $student->id,
                'is_active' => true,
                'assigned_at' => now(),
                'assigned_by_user_id' => $admin->id,
            ]);
        }

        return compact('admin', 'mathTeacher', 'physicsTeacher', 'batch', 'maths', 'physics');
    }

    protected function seedCombinedTemplate(): void
    {
        MetaWhatsAppTemplate::query()->create([
            'name' => CombinedHomeworkWhatsAppTemplate::NAME,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => CombinedHomeworkWhatsAppTemplate::BODY,
            'is_active' => true,
            'synced_at' => now(),
        ]);
    }
}

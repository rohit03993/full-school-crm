<?php

namespace Tests\Feature;

use App\Enums\AdmissionStatus;
use App\Enums\AskCrmIntent;
use App\Enums\AttendanceStatus;
use App\Enums\BatchStatus;
use App\Enums\CourseStatus;
use App\Enums\EnrollmentStatus;
use App\Enums\Gender;
use App\Enums\HomeworkCheckNotifyStatus;
use App\Enums\HomeworkCheckStatus;
use App\Enums\LeadSource;
use App\Enums\RoleName;
use App\Enums\StudentStatus;
use App\Filament\Pages\AskCrmPage;
use App\Models\AcademicSession;
use App\Models\Admission;
use App\Models\Attendance;
use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\Course;
use App\Models\CourseSubject;
use App\Models\Enquiry;
use App\Models\Enrollment;
use App\Models\FeeInstallment;
use App\Models\FeeStructure;
use App\Models\HomeworkCheck;
use App\Models\Student;
use App\Models\User;
use App\Services\AskCrmService;
use App\Services\AskCrmSessionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AskCrmServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_full_profile_snapshot_has_all_tab_sections(): void
    {
        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Aarav Bindal', withFees: true);

        $snapshot = app(\App\Services\AskCrmStudentDataService::class)->snapshot($admin, $student);

        foreach ([
            'student',
            'custom_fields',
            'profile_summary',
            'overview',
            'visits',
            'calls',
            'cases',
            'messages',
            'documents',
            'fees',
            'receipts',
            'attendance',
            'homework',
            'exams',
        ] as $section) {
            $this->assertArrayHasKey($section, $snapshot, 'Missing snapshot section: '.$section);
        }

        $this->assertSame('Aarav Bindal', $snapshot['student']['name']);
        $this->assertTrue($snapshot['fees']['can_view'] ?? false);
    }

    public function test_gemini_parsing_when_ai_enabled(): void
    {
        config([
            'ask_crm.use_ai' => true,
            'ask_crm.gemini_api_key' => 'test-key',
            'ask_crm.gemini_model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => '{"intent":"attendance_today","student_name":"Ayyush","use_context_student":false}'],
                                ],
                            ],
                        ],
                    ],
                ])
                ->push([
                    'candidates' => [
                        [
                            'content' => [
                                'parts' => [
                                    ['text' => 'Ayyush Sharma is marked **Present** today.'],
                                ],
                            ],
                        ],
                    ],
                ]),
        ]);

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'aayush aaj school aaya kya?');

        $this->assertSame(AskCrmIntent::AttendanceToday->value, $result['intent'], $result['reply']);
        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Present', $result['reply']);

        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), 'generativelanguage.googleapis.com')
                && str_contains($request->url(), 'key=test-key');
        });
    }

    public function test_falls_back_to_rules_when_gemini_fails(): void
    {
        config([
            'ask_crm.use_ai' => true,
            'ask_crm.gemini_api_key' => 'test-key',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response('error', 500),
        ]);

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'tell me attendance of aayush');

        $this->assertSame(AskCrmIntent::AttendanceToday->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Present', $result['reply']);
    }

    public function test_homework_date_follow_up_uses_last_student(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Math',
            'code' => 'M',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Math',
            'topic' => 'Worksheet',
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::Done,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $service = app(AskCrmService::class);
        $first = $service->ask($admin, 'tell me homework for abhinav singh');
        $history = [
            ['role' => 'user', 'text' => 'tell me homework for abhinav singh'],
            ['role' => 'assistant', 'text' => $first['reply']],
        ];

        $second = $service->ask(
            $admin,
            'what about the 9 aug 2026',
            $history,
            (int) $student->id,
        );

        $this->assertSame($student->id, $second['student_id'], $second['reply']);
        $this->assertStringContainsString('Done', $second['reply']);
        $this->assertStringContainsString('Abhinav', $second['reply']);
    }

    public function test_homework_follow_up_uses_last_student_without_ai(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Abhinav Singh');

        $service = app(AskCrmService::class);
        $first = $service->ask($admin, 'tell me homework for abhinav singh');

        $this->assertSame($student->id, $first['student_id'], $first['reply']);

        $history = [
            ['role' => 'user', 'text' => 'tell me homework for abhinav singh'],
            ['role' => 'assistant', 'text' => $first['reply']],
        ];

        $second = $service->ask(
            $admin,
            'what is good tell me has he done or not',
            $history,
            (int) $student->id,
        );

        $this->assertSame($student->id, $second['student_id'], $second['reply']);
        $this->assertStringNotContainsString('good has he or', strtolower($second['reply']));
        $this->assertStringContainsString('Abhinav', $second['reply']);
    }

    public function test_natural_language_attendance_question(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'tell me attendance of aayush');

        $this->assertSame(AskCrmIntent::AttendanceToday->value, $result['intent'], $result['reply']);
        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Present', $result['reply']);
    }

    public function test_attendance_today_reply_for_present_student(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'What is Ayyush attendance today?');

        $this->assertSame(AskCrmIntent::AttendanceToday->value, $result['intent']);
        $this->assertSame($student->id, $result['student_id']);
        $this->assertStringContainsString('Present', $result['reply']);
        $this->assertStringContainsString('Ayyush', $result['reply']);
    }

    public function test_cases_follow_up_this_student_remembers_context(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Aarjav Jain', withFees: true);

        $service = app(AskCrmService::class);
        $first = $service->ask($admin, 'AARJAV JAIN -- how many installments it have and of what amount');

        $this->assertSame($student->id, $first['student_id'], $first['reply']);

        $history = [
            ['role' => 'user', 'text' => 'AARJAV JAIN -- how many installments it have and of what amount'],
            ['role' => 'assistant', 'text' => $first['reply']],
        ];

        $second = $service->ask(
            $admin,
            'and cases open for this student',
            $history,
            (int) $student->id,
        );

        $this->assertSame($student->id, $second['student_id'], $second['reply']);
        $this->assertStringContainsString('Aarjav', $second['reply']);
        $this->assertStringContainsString('case', strtolower($second['reply']));
    }

    public function test_installment_question_with_dashed_student_name(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Aarjav Jain', withFees: true);

        $feeStructure = $student->activeEnrollment?->feeStructure;
        $this->assertNotNull($feeStructure);

        FeeInstallment::query()->create([
            'fee_structure_id' => $feeStructure->id,
            'label' => 'Installment 1',
            'amount' => 185000,
            'paid_amount' => 0,
            'pending_amount' => 185000,
            'sort_order' => 1,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'AARJAV JAIN -- how many installments it have and of what amount',
        );

        $this->assertSame(AskCrmIntent::FeePending->value, $result['intent'], $result['reply']);
        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('1 installment', $result['reply']);
        $this->assertStringContainsString('185,000.00', $result['reply']);
    }

    public function test_fee_pending_reply(): void
    {
        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Ayyush Sharma', withFees: true);

        $result = app(AskCrmService::class)->ask($admin, 'How much fee pending for Ayyush?');

        $this->assertSame(AskCrmIntent::FeePending->value, $result['intent']);
        $this->assertStringContainsString('2,500.00', $result['reply']);
    }

    public function test_homework_not_done_this_week_reply(): void
    {
        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Math',
            'code' => 'M',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Math',
            'topic' => 'Worksheet',
            'checked_on' => now()->toDateString(),
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'Homework not done for Ayyush this week?');

        $this->assertSame(AskCrmIntent::HomeworkWeek->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
    }

    public function test_homework_name_dash_and_date_in_one_question(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'ABHINAV SINGH - homework for 9 aug 2026',
        );

        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
        $this->assertStringContainsString('09 Aug 2026', $result['reply']);
        $this->assertStringContainsString('Maths', $result['reply']);
    }

    public function test_homework_status_shows_recent_not_done_outside_calendar_week(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'ABHINAV SINGH — I need homework status',
        );

        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
        $this->assertStringContainsString('09 Aug 2026', $result['reply']);
    }

    public function test_ask_about_someone_else_clears_context(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Abhinav Singh');

        $service = app(AskCrmService::class);
        $first = $service->ask($admin, 'tell me homework for abhinav singh');

        $this->assertSame($student->id, $first['student_id']);

        $second = $service->ask(
            $admin,
            'ask about someone else',
            [
                ['role' => 'user', 'text' => 'tell me homework for abhinav singh'],
                ['role' => 'assistant', 'text' => $first['reply']],
            ],
            (int) $student->id,
        );

        $this->assertTrue($second['clear_context'] ?? false);
        $this->assertNull($second['student_id']);
        $this->assertStringContainsString('cleared', strtolower($second['reply']));
    }

    public function test_new_student_name_overrides_previous_context(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$abhinav, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $ayyush = Student::query()->create([
            'name' => 'Ayyush Sharma',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500099',
            'status' => StudentStatus::Enrolled,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $ayyush->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $admin->id,
        ]);

        $service = app(AskCrmService::class);
        $service->ask($admin, 'homework for abhinav singh', [], (int) $abhinav->id);

        $result = $service->ask(
            $admin,
            'attendance for ayyush sharma today',
            [],
            (int) $abhinav->id,
        );

        $this->assertSame($ayyush->id, $result['student_id'], $result['reply']);
    }

    public function test_widget_clear_student_context_keeps_session(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);
        [$student] = $this->createEnrolledStudent('Aarjav Jain', withFees: true);

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->set('message', 'How much fee pending for Aarjav Jain?')
            ->call('send')
            ->assertSet('lastStudentId', $student->id)
            ->call('clearStudentContext')
            ->assertSet('lastStudentId', null)
            ->assertSet('lastStudentName', null)
            ->assertSet('hasActiveSession', true);

        $this->assertTrue(app(AskCrmSessionService::class)->isActive());
        $this->assertNull(app(AskCrmSessionService::class)->load()['last_student_id'] ?? null);
    }

    public function test_widget_clear_chat_resets_messages_and_keeps_session(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);
        [$student] = $this->createEnrolledStudent('Aarjav Jain', withFees: true);

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->set('message', 'How much fee pending for Aarjav Jain?')
            ->call('send')
            ->assertSet('lastStudentId', $student->id)
            ->assertSet('hasActiveSession', true)
            ->call('clearChat')
            ->assertSet('lastStudentId', null)
            ->assertSet('lastStudentName', null)
            ->assertSet('hasActiveSession', true);

        $session = app(AskCrmSessionService::class);
        $this->assertTrue($session->isActive());
        $this->assertNull($session->load()['last_student_id'] ?? null);
        $this->assertCount(1, $session->load()['messages'] ?? []);
        $this->assertStringContainsString('Ask CRM', (string) ($session->load()['messages'][0]['text'] ?? ''));
    }

    public function test_homework_status_with_full_name_uses_context_student(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'ABHINAV SINGH what is the homework status',
            [],
            (int) $student->id,
        );

        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
        $this->assertStringNotContainsString('more than one student', strtolower($result['reply']));
        $this->assertStringNotContainsString('matching', strtolower($result['reply']));
    }

    public function test_ai_wrong_name_is_overridden_by_full_name_in_question(): void
    {
        config([
            'ask_crm.use_ai' => true,
            'ask_crm.gemini_api_key' => 'test-key',
            'ask_crm.gemini_model' => 'gemini-2.0-flash',
        ]);

        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"intent":"homework_week","student_name":"SINGH","use_context_student":false}'],
                            ],
                        ],
                    ],
                ],
            ]),
        ]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'ABHINAV SINGH what is the homework status',
        );

        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
        $this->assertStringNotContainsString('more than one student', strtolower($result['reply']));
    }

    public function test_homework_reply_includes_parent_whatsapp_copy_and_profile_link(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Abhinav Singh');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => "Today's homework",
            'checked_on' => '2026-08-09',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'ABHINAV SINGH homework status — whatsapp message for parent',
        );

        $this->assertSame($student->id, $result['student_id'], $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
        $this->assertStringContainsString('Parent WhatsApp copy', $result['reply']);
        $this->assertStringContainsString('Dear Parent', $result['reply']);
        $this->assertStringContainsString('[Open profile](', $result['reply']);
        $this->assertStringContainsString('[Homework](', $result['reply']);
    }

    public function test_fee_reply_includes_parent_whatsapp_copy_when_requested(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Ayyush Sharma', withFees: true);

        $result = app(AskCrmService::class)->ask(
            $admin,
            'How much fee pending for Ayyush Sharma — whatsapp message for parent',
        );

        $this->assertSame(AskCrmIntent::FeePending->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('2,500.00', $result['reply']);
        $this->assertStringContainsString('Dear Parent', $result['reply']);
        $this->assertStringContainsString('Tuition fee pending', $result['reply']);
        $this->assertStringContainsString('[Fees](', $result['reply']);
    }

    public function test_my_tasks_question_does_not_search_for_student_name(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();

        $result = app(AskCrmService::class)->ask($admin, 'Tell me about my tasks');

        $this->assertSame(AskCrmIntent::MyTasks->value, $result['intent'], $result['reply']);
        $this->assertNull($result['student_id']);
        $this->assertStringContainsString('Call queue due', $result['reply']);
        $this->assertStringNotContainsString('couldn’t find a student', strtolower($result['reply']));
        $this->assertStringNotContainsString('matching', strtolower($result['reply']));
    }

    public function test_how_to_fee_payment_question(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();

        $result = app(AskCrmService::class)->ask($admin, 'How do I record a fee payment?');

        $this->assertSame(AskCrmIntent::HowTo->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Add Payment', $result['reply']);
        $this->assertStringContainsString('Fees', $result['reply']);
    }

    public function test_batch_absent_question_lists_students(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$presentStudent, $batch] = $this->createEnrolledStudent('Present Student');
        $absentStudent = Student::query()->create([
            'name' => 'Absent Student',
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500088',
            'status' => StudentStatus::Enrolled,
        ]);

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $absentStudent->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $admin->id,
        ]);

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $presentStudent->id,
            'attendance_date' => '2026-08-10',
            'status' => AttendanceStatus::Present,
            'marked_by_user_id' => $admin->id,
        ]);

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $absentStudent->id,
            'attendance_date' => '2026-08-10',
            'status' => AttendanceStatus::Absent,
            'marked_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'Who is absent in 10-A today?');

        $this->assertSame(AskCrmIntent::BatchStatus->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Absent Student', $result['reply']);
        $this->assertStringContainsString('Absent', $result['reply']);
    }

    public function test_batch_homework_not_done_question(): void
    {
        config(['ask_crm.use_ai' => false]);

        $this->travelTo('2026-08-10 10:00:00');

        $admin = $this->createSuperAdmin();
        [$student, $batch] = $this->createEnrolledStudent('Homework Skipper');
        $subject = CourseSubject::query()->create([
            'course_id' => $batch->course_id,
            'name' => 'Maths',
            'code' => 'M01',
            'default_max_marks' => 100,
            'sort_order' => 1,
            'is_active' => true,
        ]);

        HomeworkCheck::query()->create([
            'student_id' => $student->id,
            'batch_id' => $batch->id,
            'course_subject_id' => $subject->id,
            'subject_name' => 'Maths',
            'topic' => 'Algebra',
            'checked_on' => '2026-08-10',
            'status' => HomeworkCheckStatus::NotDone,
            'notify_status' => HomeworkCheckNotifyStatus::Failed,
            'created_by_user_id' => $admin->id,
        ]);

        $result = app(AskCrmService::class)->ask($admin, 'homework not done in 10-A today');

        $this->assertSame(AskCrmIntent::BatchStatus->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Homework Skipper', $result['reply']);
        $this->assertStringContainsString('Not Done', $result['reply']);
    }

    public function test_hinglish_my_tasks_and_student_fee(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        [$student] = $this->createEnrolledStudent('Ayyush Sharma', withFees: true);

        $tasks = app(AskCrmService::class)->ask($admin, 'aaj mere tasks kya hain');
        $this->assertSame(AskCrmIntent::MyTasks->value, $tasks['intent'], $tasks['reply']);

        $fee = app(AskCrmService::class)->ask($admin, 'Ayyush Sharma ka fee kitna pending hai');
        $this->assertSame($student->id, $fee['student_id'], $fee['reply']);
        $this->assertStringContainsString('2,500.00', $fee['reply']);
    }

    public function test_how_to_assign_batch_question(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        $result = app(AskCrmService::class)->ask($admin, 'How do I assign a batch?');

        $this->assertSame(AskCrmIntent::HowTo->value, $result['intent'], $result['reply']);
        $this->assertStringContainsString('Assign Batch', $result['reply']);
    }

    public function test_ask_crm_page_is_accessible(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);

        Livewire::test(AskCrmPage::class)
            ->assertSuccessful()
            ->assertSee('Ask CRM is always one tap away')
            ->assertSee('ask-crm-toggle', false);
    }

    public function test_ask_crm_session_persists_until_end_chat(): void
    {
        config(['ask_crm.use_ai' => false]);

        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);
        [$student] = $this->createEnrolledStudent('Aarjav Jain', withFees: true);

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->set('message', 'How much fee pending for Aarjav Jain?')
            ->call('send')
            ->assertSet('lastStudentId', $student->id)
            ->assertSet('hasActiveSession', true);

        $this->assertTrue(app(AskCrmSessionService::class)->isActive());

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->assertSet('lastStudentId', $student->id)
            ->assertSet('hasActiveSession', true)
            ->assertSet('lastStudentName', 'Aarjav Jain');

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->call('close')
            ->assertSet('hasActiveSession', false)
            ->assertSet('lastStudentId', null);

        $this->assertFalse(app(AskCrmSessionService::class)->isActive());
    }

    public function test_ask_crm_floating_widget_answers_questions(): void
    {
        $admin = $this->createSuperAdmin();
        $this->actingAs($admin);
        [$student, $batch] = $this->createEnrolledStudent('Ayyush Sharma');

        Attendance::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'attendance_date' => now()->toDateString(),
            'status' => AttendanceStatus::Present,
            'checked_in_at' => now()->setTime(9, 12),
            'marked_by_user_id' => $admin->id,
        ]);

        Livewire::test(\App\Livewire\AskCrmChatWidget::class)
            ->call('toggle')
            ->assertSet('open', true)
            ->set('message', 'What is Ayyush attendance today?')
            ->call('send')
            ->assertSee('Present');
    }

    /**
     * @return array{0: Student, 1: Batch}
     */
    private function createEnrolledStudent(string $name, bool $withFees = false): array
    {
        $staff = User::factory()->create(['is_active' => true]);

        $session = AcademicSession::query()->create([
            'name' => '2026-27',
            'code' => '2026-27',
            'starts_on' => '2026-04-01',
            'ends_on' => '2027-03-31',
            'is_current' => true,
            'is_active' => true,
        ]);

        $course = Course::query()->create([
            'name' => 'Class 10',
            'code' => 'ASK-10',
            'programme_category' => 'school',
            'duration' => 12,
            'duration_type' => 'months',
            'fee' => 10000,
            'status' => CourseStatus::Active,
        ]);

        $batch = Batch::query()->create([
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'name' => '10-A',
            'trainer_user_id' => $staff->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-12-31',
            'status' => BatchStatus::Active,
        ]);

        $student = Student::query()->create([
            'name' => $name,
            'father_name' => 'Parent',
            'date_of_birth' => '2010-01-01',
            'gender' => Gender::Male,
            'mobile' => '9876500011',
            'status' => StudentStatus::Enrolled,
        ]);

        $enquiry = Enquiry::query()->create([
            'student_id' => $student->id,
            'enquiry_number' => 'CRM-ENQ-ASK',
            'course_id' => $course->id,
            'lead_source' => LeadSource::WalkIn,
            'meeting_for' => 'school',
            'visit_type' => 'first_visit',
            'latest_visit_status' => 'interested',
        ]);

        $admission = Admission::query()->create([
            'student_id' => $student->id,
            'enquiry_id' => $enquiry->id,
            'admission_number' => 'CRM-ADM-ASK',
            'status' => AdmissionStatus::Approved,
        ]);

        $enrollment = Enrollment::query()->create([
            'student_id' => $student->id,
            'admission_id' => $admission->id,
            'course_id' => $course->id,
            'academic_session_id' => $session->id,
            'enrollment_number' => 'ROLL-ASK-01',
            'enrolled_at' => now(),
            'status' => EnrollmentStatus::Enrolled,
            'is_active' => true,
        ]);

        if ($withFees) {
            FeeStructure::query()->create([
                'enrollment_id' => $enrollment->id,
                'course_fee' => 10000,
                'discount_amount' => 0,
                'net_fee' => 10000,
                'paid_amount' => 7500,
                'pending_amount' => 2500,
            ]);
        }

        BatchStudent::query()->create([
            'batch_id' => $batch->id,
            'student_id' => $student->id,
            'is_active' => true,
            'assigned_at' => now(),
            'assigned_by_user_id' => $staff->id,
        ]);

        return [$student->fresh(['activeBatchStudent.batch', 'activeEnrollment.feeStructure']), $batch];
    }

    private function createSuperAdmin(): User
    {
        Role::findOrCreate(RoleName::SuperAdmin->value);

        $user = User::factory()->create(['is_active' => true]);
        $user->assignRole(RoleName::SuperAdmin->value);

        return $user;
    }
}

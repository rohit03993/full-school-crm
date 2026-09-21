<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Models\User;
use App\Services\MetaWhatsAppConversationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MetaWhatsAppConversationServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_lists_recent_conversations_by_latest_message_per_phone(): void
    {
        $kapil = Student::query()->create([
            'name' => 'Kapil',
            'mobile' => '8320936486',
            'status' => StudentStatus::Enrolled,
        ]);

        $amit = Student::query()->create([
            'name' => 'Amit Verma',
            'mobile' => '9811000009',
            'status' => StudentStatus::Enrolled,
        ]);

        $kapilOldAt = now()->subHours(3);
        $kapilReplyAt = now()->subHour();
        $amitAt = now()->subMinutes(20);

        $this->createTimedMetaMessage([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936486',
            'student_id' => $kapil->id,
            'body_preview' => 'Older Kapil message',
            'status' => 'read',
            'status_at' => $kapilOldAt,
        ], $kapilOldAt);

        $this->createTimedMetaMessage([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918320936486',
            'student_id' => $kapil->id,
            'body_preview' => 'Latest Kapil reply',
            'status' => 'received',
            'status_at' => $kapilReplyAt,
        ], $kapilReplyAt);

        $this->createTimedMetaMessage([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000009',
            'student_id' => $amit->id,
            'body_preview' => 'Amit attendance update',
            'status' => 'delivered',
            'status_at' => $amitAt,
        ], $amitAt);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations();

        $this->assertCount(2, $conversations);
        $this->assertSame($amit->id, $conversations->first()->studentId);
        $this->assertSame('Latest Kapil reply', $conversations->last()->preview);
        $this->assertTrue($conversations->last()->needsReply);
        $this->assertFalse($conversations->last()->lastSendFailed);
        $this->assertSame('student', $conversations->first()->contactKind);
    }

    public function test_marks_chat_failed_from_last_school_send_even_if_parent_replied(): void
    {
        $failed = Student::query()->create([
            'name' => 'Failed Number',
            'mobile' => '9811000301',
            'status' => StudentStatus::Enquiry,
        ]);

        $ok = Student::query()->create([
            'name' => 'Delivered Number',
            'mobile' => '9811000302',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000301',
            'student_id' => $failed->id,
            'body_preview' => 'Homework did not reach this number',
            'status' => 'failed',
            'status_at' => now()->subMinutes(5),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000301',
            'student_id' => $failed->id,
            'body_preview' => 'Old parent reply',
            'status' => 'received',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000302',
            'student_id' => $ok->id,
            'body_preview' => 'Homework delivered',
            'status' => 'delivered',
            'status_at' => now(),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations()->keyBy('phone');

        $this->assertTrue($conversations['919811000301']->lastSendFailed);
        $this->assertFalse($conversations['919811000302']->lastSendFailed);
    }

    public function test_later_delivered_school_send_clears_failed_flag(): void
    {
        $student = Student::query()->create([
            'name' => 'Retry Number',
            'mobile' => '9811000303',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000303',
            'student_id' => $student->id,
            'body_preview' => 'First homework failed',
            'status' => 'failed',
            'status_at' => now()->subMinutes(10),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000303',
            'student_id' => $student->id,
            'body_preview' => 'Second homework delivered',
            'status' => 'delivered',
            'status_at' => now(),
        ]);

        $conversation = app(MetaWhatsAppConversationService::class)->recentConversations()->first();

        $this->assertFalse($conversation?->lastSendFailed);
    }

    public function test_includes_unknown_numbers_without_student(): void
    {
        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919999888877',
            'student_id' => null,
            'body_preview' => 'Hello from unknown contact',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations();

        $this->assertCount(1, $conversations);
        $this->assertNull($conversations->first()->studentId);
        $this->assertSame('Unknown contact', $conversations->first()->studentName);
        $this->assertFalse($conversations->first()->isLinked);
        $this->assertSame('unknown', $conversations->first()->contactKind);
        $this->assertSame('Hello from unknown contact', $conversations->first()->preview);
    }

    public function test_resolves_staff_contact_by_mobile(): void
    {
        $staff = User::factory()->create([
            'name' => 'Khushi Mam',
            'mobile' => '8109432345',
            'is_active' => true,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918109432345',
            'student_id' => null,
            'body_preview' => 'OTP login code',
            'status' => 'delivered',
            'status_at' => now(),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations();

        $this->assertCount(1, $conversations);
        $this->assertSame('Khushi Mam', $conversations->first()->studentName);
        $this->assertSame('staff', $conversations->first()->contactKind);
        $this->assertSame(['Staff'], $conversations->first()->contactTags);
        $this->assertSame($staff->id, $conversations->first()->staffUserId);
        $this->assertTrue($conversations->first()->isLinked);
    }

    public function test_resolves_lead_and_student_tags_from_student_status(): void
    {
        $lead = Student::query()->create([
            'name' => 'Lead Parent',
            'mobile' => '9811000011',
            'status' => StudentStatus::Enquiry,
        ]);

        $student = Student::query()->create([
            'name' => 'Enrolled Kid',
            'mobile' => '9811000012',
            'status' => StudentStatus::Enrolled,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000011',
            'student_id' => null,
            'body_preview' => 'Lead hello',
            'status' => 'received',
            'status_at' => now(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000012',
            'student_id' => null,
            'body_preview' => 'Student hello',
            'status' => 'received',
            'status_at' => now()->subMinute(),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations();
        $byPhone = $conversations->keyBy('phone');

        $this->assertSame('Lead Parent', $byPhone['919811000011']->studentName);
        $this->assertSame('lead', $byPhone['919811000011']->contactKind);
        $this->assertSame(['Lead'], $byPhone['919811000011']->contactTags);
        $this->assertSame($lead->id, $byPhone['919811000011']->studentId);

        $this->assertSame('Enrolled Kid', $byPhone['919811000012']->studentName);
        $this->assertSame('student', $byPhone['919811000012']->contactKind);
        $this->assertSame(['Student'], $byPhone['919811000012']->contactTags);
        $this->assertSame($student->id, $byPhone['919811000012']->studentId);
    }

    public function test_search_filters_conversations(): void
    {
        $student = Student::query()->create([
            'name' => 'Sneha Gupta',
            'mobile' => '9811000008',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '919811000008',
            'student_id' => $student->id,
            'body_preview' => 'Need homework details',
            'status' => 'received',
            'status_at' => now(),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations('homework');

        $this->assertCount(1, $conversations);
        $this->assertSame($student->id, $conversations->first()->studentId);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    protected function createTimedMetaMessage(array $attributes, \Illuminate\Support\Carbon $at): MetaWhatsAppMessage
    {
        $message = MetaWhatsAppMessage::query()->create($attributes);
        $message->forceFill([
            'created_at' => $at,
            'updated_at' => $at,
        ])->save();

        return $message->refresh();
    }
}

<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
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

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '918320936486',
            'student_id' => $kapil->id,
            'body_preview' => 'Older Kapil message',
            'status' => 'read',
            'status_at' => now()->subHours(3),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Inbound->value,
            'phone' => '918320936486',
            'student_id' => $kapil->id,
            'body_preview' => 'Latest Kapil reply',
            'status' => 'received',
            'status_at' => now()->subHour(),
        ]);

        MetaWhatsAppMessage::query()->create([
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919811000009',
            'student_id' => $amit->id,
            'body_preview' => 'Amit attendance update',
            'status' => 'delivered',
            'status_at' => now()->subMinutes(20),
        ]);

        $conversations = app(MetaWhatsAppConversationService::class)->recentConversations();

        $this->assertCount(2, $conversations);
        $this->assertSame($amit->id, $conversations->first()->studentId);
        $this->assertSame('Latest Kapil reply', $conversations->last()->preview);
        $this->assertTrue($conversations->last()->needsReply);
    }

    public function test_search_filters_conversations(): void
    {
        $student = Student::query()->create([
            'name' => 'Sneha Gupta',
            'mobile' => '9811000008',
            'status' => StudentStatus::Enrolled,
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
}

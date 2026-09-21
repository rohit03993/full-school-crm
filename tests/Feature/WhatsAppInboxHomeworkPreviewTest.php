<?php

namespace Tests\Feature;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\StudentStatus;
use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Student;
use App\Services\MetaWhatsAppMessageLogger;
use App\Services\StudentWhatsAppThreadService;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use App\Support\WhatsAppInboxBodyPreview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppInboxHomeworkPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_logger_keeps_full_homework_text_in_inbox_copy(): void
    {
        $links = 'Physics (PHY): https://motionagra.in/h/GEQV4mOoz67WYaM8XDxSNvfFaRbCF9l9AzWGlpqDmLZOUK8e'
            .' | Chemistry (CHE): https://motionagra.in/h/Avy2lgiBclnTwSpM8VlcOufxDtFHIHkqBgWZnkgGbZtPp4k'
            .' | Maths (MATH): https://motionagra.in/h/BphGVOI5eUhicZMwoB5OPE9qJsICZuQOvrVZzj56YRJX';

        $full = str_replace(
            ['{{1}}', '{{2}}', '{{3}}', '{{4}}'],
            ['MAYANK KUMAR', '26175000182', 'Class 11 - Section JEE B - 21 Sep 2026', $links],
            CombinedHomeworkWhatsAppTemplate::BODY,
        );

        $this->assertGreaterThan(500, mb_strlen($full));

        app(MetaWhatsAppMessageLogger::class)->recordOutbound(
            '9876543210',
            'wamid.HOMEWORK1',
            CombinedHomeworkWhatsAppTemplate::NAME,
            'en',
            ['MAYANK KUMAR', '26175000182', 'Class 11 - Section JEE B - 21 Sep 2026', $links],
            MetaWhatsAppMessageStatus::Sent,
            null,
            null,
            null,
            $full,
        );

        $saved = (string) MetaWhatsAppMessage::query()->where('wamid', 'wamid.HOMEWORK1')->value('body_preview');

        $this->assertSame($full, $saved);
        $this->assertStringContainsString('Kindly ensure it is completed on time. Thank you.', $saved);
        $this->assertLessThanOrEqual(WhatsAppInboxBodyPreview::MAX_LENGTH, mb_strlen($saved));
    }

    public function test_inbox_thread_finishes_old_500_letter_homework_cut(): void
    {
        $student = Student::query()->create([
            'name' => 'MAYANK KUMAR',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enquiry,
        ]);

        MetaWhatsAppTemplate::query()->create([
            'name' => CombinedHomeworkWhatsAppTemplate::NAME,
            'language' => 'en',
            'status' => 'APPROVED',
            'param_count' => 4,
            'body' => CombinedHomeworkWhatsAppTemplate::BODY,
            'is_active' => true,
            'synced_at' => now(),
        ]);

        $cut = mb_substr(
            "Dear Parent,\nToday's homework for your ward MAYANK KUMAR (Roll No: 26175000182) has been assigned for Class 11 - Section JEE B - 21 Sep 2026.\n\n"
            ."Please open each subject below to view or download. No login is required:\n"
            .'Physics (PHY): https://motionagra.in/h/GEQV4mOoz67WYaM8XDxSNvfFaRbCF9l9AzWGlpqDmLZOUK8e | Chemistry (CHE): https://motionagra.in/h/Avy2lgiBclnTwSpM8VlcOufxDtFHIHkqBgWZnkgGbZtPp4k | Maths (MATH): https://motionagra.in/h/BphGVOI5eUhicZMwoB5OPE9qJsICZuQOvrVZzj56YRJX'
            ."\n\nKindly ensur",
            0,
            500,
        );

        MetaWhatsAppMessage::query()->create([
            'wamid' => 'wamid.CUT500',
            'direction' => MetaWhatsAppMessageDirection::Outbound->value,
            'phone' => '919876543210',
            'student_id' => $student->id,
            'template_name' => CombinedHomeworkWhatsAppTemplate::NAME,
            'language' => 'en',
            'body_preview' => $cut,
            'message_type' => 'text',
            'status' => MetaWhatsAppMessageStatus::Sent->value,
            'status_at' => now(),
        ]);

        $body = app(StudentWhatsAppThreadService::class)
            ->threadForStudent($student)
            ->first()
            ?->body;

        $this->assertIsString($body);
        $this->assertStringContainsString('Kindly ensure it is completed on time. Thank you.', $body);
        $this->assertStringContainsString('motionagra.in/h/', $body);
    }
}

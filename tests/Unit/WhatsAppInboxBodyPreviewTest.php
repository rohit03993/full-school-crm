<?php

namespace Tests\Unit;

use App\Support\CombinedHomeworkWhatsAppTemplate;
use App\Support\WhatsAppInboxBodyPreview;
use PHPUnit\Framework\TestCase;

class WhatsAppInboxBodyPreviewTest extends TestCase
{
    public function test_clip_keeps_homework_length_beyond_old_500_limit(): void
    {
        $text = str_repeat('a', 600).' Kindly ensure it is completed on time. Thank you.';

        $this->assertSame($text, WhatsAppInboxBodyPreview::clip($text));
        $this->assertSame(8000, mb_strlen(WhatsAppInboxBodyPreview::clip(str_repeat('b', 9000))));
    }

    public function test_appends_cut_homework_closing_line(): void
    {
        $preview = "Dear Parent,\nToday's homework\nPhysics: https://motionagra.in/h/aaa | Maths: https://motionagra.in/h/bbb\n\nKindly ensur";

        $fixed = WhatsAppInboxBodyPreview::appendMissingTemplateSuffix(
            $preview,
            CombinedHomeworkWhatsAppTemplate::BODY,
        );

        $this->assertStringEndsWith('Kindly ensure it is completed on time. Thank you.', $fixed);
        $this->assertStringContainsString('https://motionagra.in/h/aaa', $fixed);
    }

    public function test_leaves_complete_homework_text_alone(): void
    {
        $preview = CombinedHomeworkWhatsAppTemplate::BODY;
        $preview = str_replace('{{1}}', 'Mayank', $preview);
        $preview = str_replace('{{2}}', '123', $preview);
        $preview = str_replace('{{3}}', 'Class 11', $preview);
        $preview = str_replace('{{4}}', 'Maths: https://example.com/h/token', $preview);

        $this->assertSame(
            $preview,
            WhatsAppInboxBodyPreview::appendMissingTemplateSuffix($preview, CombinedHomeworkWhatsAppTemplate::BODY),
        );
    }
}

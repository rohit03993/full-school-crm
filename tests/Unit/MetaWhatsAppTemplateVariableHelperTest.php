<?php

namespace Tests\Unit;

use App\Support\MetaWhatsAppTemplateVariableHelper;
use PHPUnit\Framework\TestCase;

class MetaWhatsAppTemplateVariableHelperTest extends TestCase
{
    public function test_sync_rows_from_body_detects_four_variables(): void
    {
        $body = "Hello {{1}}, roll {{2}}, at {{3}} on {{4}}.";

        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody($body);

        $this->assertCount(4, $rows);
        $this->assertSame(1, $rows[0]['index']);
        $this->assertSame('Student name', $rows[0]['label']);
        $this->assertSame('Rohit Sharma', $rows[0]['example']);
        $this->assertSame(4, $rows[3]['index']);
        $this->assertSame('Date', $rows[3]['label']);
    }

    public function test_sync_preserves_existing_sample_values(): void
    {
        $body = 'Hi {{1}}, roll {{2}}.';

        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody($body, [
            ['index' => 1, 'label' => 'Student name', 'example' => 'Custom Name'],
            ['index' => 2, 'label' => 'Roll number', 'example' => 'ROLL-99'],
        ]);

        $this->assertSame('Custom Name', $rows[0]['example']);
        $this->assertSame('ROLL-99', $rows[1]['example']);
    }

    public function test_fee_reminder_body_uses_fee_sample_labels(): void
    {
        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            \App\Support\FeeReminderWhatsAppTemplate::BODY,
            [],
            'fee_reminder',
        );

        $this->assertCount(4, $rows);
        $this->assertSame('Institute name', $rows[0]['label']);
        $this->assertSame('B.D.M. Kanya Degree College', $rows[0]['example']);
        $this->assertSame('Student name', $rows[1]['label']);
        $this->assertSame('Pending amount', $rows[2]['label']);
        $this->assertSame('Due date', $rows[3]['label']);
    }

    public function test_homework_not_done_body_uses_homework_sample_labels(): void
    {
        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            \App\Support\HomeworkNotDoneWhatsAppTemplate::BODY,
            [],
            'homework_not_done',
        );

        $this->assertCount(5, $rows);
        $this->assertSame('Student name', $rows[0]['label']);
        $this->assertSame('Class / section', $rows[1]['label']);
        $this->assertSame('Subject', $rows[2]['label']);
        $this->assertSame('Homework topic', $rows[3]['label']);
        $this->assertSame('Institute name', $rows[4]['label']);
    }

    public function test_rows_to_examples_csv_in_order(): void
    {
        $csv = MetaWhatsAppTemplateVariableHelper::rowsToExamplesCsv([
            ['index' => 2, 'example' => '12-A'],
            ['index' => 1, 'example' => 'Rohit'],
        ]);

        $this->assertSame('Rohit, 12-A', $csv);
    }

    public function test_preview_replaces_placeholders(): void
    {
        $preview = MetaWhatsAppTemplateVariableHelper::previewBody(
            'Dear {{1}}, roll {{2}}.',
            [
                ['index' => 1, 'example' => 'Amit'],
                ['index' => 2, 'example' => '10-B'],
            ],
        );

        $this->assertSame('Dear Amit, roll 10-B.', $preview);
    }
}

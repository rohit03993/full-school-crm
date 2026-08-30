<?php

namespace Tests\Unit;

use App\Support\FeeReminderWhatsAppTemplate;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use App\Support\HomeworkShareWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateVariableHelper;
use PHPUnit\Framework\TestCase;

class MetaWhatsAppTemplateVariableHelperTest extends TestCase
{
    public function test_sync_rows_from_body_uses_generic_labels_by_default(): void
    {
        $body = "Hello {{1}}, roll {{2}}, at {{3}} on {{4}}.";

        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody($body);

        $this->assertCount(4, $rows);
        $this->assertSame(1, $rows[0]['index']);
        $this->assertSame('Variable 1', $rows[0]['label']);
        $this->assertSame('Sample 1', $rows[0]['example']);
        $this->assertSame(3, $rows[2]['index']);
        $this->assertSame('Variable 3', $rows[2]['label']);
        $this->assertSame('Sample 3', $rows[2]['example']);
        $this->assertSame('Variable 4', $rows[3]['label']);
    }

    public function test_custom_homework_wording_without_preset_name_stays_generic(): void
    {
        $body = "Dear Parent,\nHomework for {{1}} (Roll: {{2}})\nTitle: {{3}}\nOpen homework:\n{{4}}";

        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody($body, [], 'parent_update');

        $this->assertSame('Variable 3', $rows[2]['label']);
        $this->assertSame('Variable 4', $rows[3]['label']);
        $this->assertSame('Sample 3', $rows[2]['example']);
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
            FeeReminderWhatsAppTemplate::BODY,
            [],
            'fee_reminder',
        );

        $this->assertCount(4, $rows);
        $this->assertSame('Institute name', $rows[0]['label']);
        $this->assertSame('Springdale Public School', $rows[0]['example']);
        $this->assertSame('Student name', $rows[1]['label']);
        $this->assertSame('Pending amount', $rows[2]['label']);
        $this->assertSame('Due date', $rows[3]['label']);
    }

    public function test_upcoming_and_due_preset_names_fill_distinct_bodies(): void
    {
        $upcoming = FeeReminderWhatsAppTemplate::formPresetForName('fee_reminder_upcoming');
        $due = FeeReminderWhatsAppTemplate::formPresetForName('fee_reminder_due');
        $overdue = FeeReminderWhatsAppTemplate::formPresetForName('fee_reminder_overdue');

        $this->assertNotNull($upcoming);
        $this->assertNotNull($due);
        $this->assertNotNull($overdue);
        $this->assertSame(FeeReminderWhatsAppTemplate::BODY_UPCOMING, $upcoming['body_text']);
        $this->assertSame(FeeReminderWhatsAppTemplate::BODY_DUE, $due['body_text']);
        $this->assertSame(FeeReminderWhatsAppTemplate::BODY_OVERDUE, $overdue['body_text']);
        $this->assertStringContainsString('Please pay on or before the due date', $upcoming['body_text']);
        $this->assertStringContainsString('Please complete the payment today', $due['body_text']);
        $this->assertStringContainsString('The due date has passed', $overdue['body_text']);
    }

    public function test_homework_not_done_body_uses_homework_sample_labels(): void
    {
        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            HomeworkNotDoneWhatsAppTemplate::BODY,
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

    public function test_homework_share_preset_name_uses_share_labels(): void
    {
        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            HomeworkShareWhatsAppTemplate::BODY,
            [],
            'homework_update',
        );

        $this->assertCount(4, $rows);
        $this->assertSame('Student name', $rows[0]['label']);
        $this->assertSame('Homework title', $rows[2]['label']);
        $this->assertSame('Public homework link', $rows[3]['label']);
        $this->assertStringContainsString('/h/', $rows[3]['example']);
    }

    public function test_student_punch_in_preset_fills_body_and_samples(): void
    {
        $this->assertTrue(\App\Support\StudentPunchWhatsAppTemplate::looksLikeInName('punch_in'));
        $this->assertTrue(\App\Support\StudentPunchWhatsAppTemplate::looksLikeInName('manual_in'));
        $this->assertFalse(\App\Support\StudentPunchWhatsAppTemplate::looksLikeInName('staff_punch_in'));

        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            \App\Support\StudentPunchWhatsAppTemplate::IN_BODY,
            [],
            'punch_in',
        );

        $this->assertCount(5, $rows);
        $this->assertSame('Student name', $rows[0]['label']);
        $this->assertSame('Roll number', $rows[1]['label']);
        $this->assertSame('Attendance status', $rows[4]['label']);
    }

    public function test_student_punch_out_preset_name_is_detected(): void
    {
        $this->assertTrue(\App\Support\StudentPunchWhatsAppTemplate::looksLikeOutName('punch_out'));
        $this->assertTrue(\App\Support\StudentPunchWhatsAppTemplate::looksLikeOutName('manual_out'));
        $this->assertFalse(\App\Support\StudentPunchWhatsAppTemplate::looksLikeOutName('staff_punch_out'));
    }

    public function test_login_otp_preset_uses_otp_sample_label(): void
    {
        $rows = MetaWhatsAppTemplateVariableHelper::syncRowsFromBody(
            \App\Support\LoginOtpWhatsAppTemplate::BODY,
            [],
            'login_otp',
        );

        $this->assertCount(1, $rows);
        $this->assertSame('4-digit OTP', $rows[0]['label']);
        $this->assertSame('4821', $rows[0]['example']);
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

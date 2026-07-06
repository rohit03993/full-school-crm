<?php

namespace Tests\Unit;

use App\Enums\StudentStatus;
use App\Models\Student;
use App\Models\WhatsAppTemplate;
use App\Support\StudentWhatsAppTemplateComposer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentWhatsAppTemplateComposerTest extends TestCase
{
    use RefreshDatabase;

    public function test_compose_builds_fields_for_each_template_parameter(): void
    {
        $student = Student::query()->create([
            'name' => 'Kapil Sharma',
            'mobile' => '8320936488',
            'status' => StudentStatus::Enquiry,
        ]);

        $template = WhatsAppTemplate::query()->create([
            'name' => 'fee_reminder',
            'param_count' => 3,
            'param_mappings' => ['student.name', 'student.mobile', null],
            'body' => 'Hello {{1}}, mobile {{2}}. {{3}}',
            'provider_meta' => [
                'body_variables' => ['student_name', 'mobile', 'due_date'],
            ],
            'is_active' => true,
        ]);

        $compose = app(StudentWhatsAppTemplateComposer::class)->compose($template, $student, null);

        $this->assertSame(3, $compose['param_count']);
        $this->assertCount(3, $compose['fields']);
        $this->assertSame('Kapil Sharma', $compose['defaults'][0]);
        $this->assertSame('8320936488', $compose['defaults'][1]);
        $this->assertStringContainsString('Kapil Sharma', (string) $compose['preview_body']);
    }
}

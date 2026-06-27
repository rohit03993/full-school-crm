<?php

namespace Tests\Feature;

use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppTemplateParamMappingInferrerTest extends TestCase
{
    use RefreshDatabase;

    public function test_infers_student_batch_and_campaign_fields_from_body_variables(): void
    {
        $sources = WhatsAppTemplateParamMappingInferrer::infer(
            ['name', 'batch', 'date'],
            3,
        );

        $this->assertSame([
            'student.name',
            'batch.name',
            'campaign.date',
        ], $sources);
    }

    public function test_infers_attendance_check_in_template_variables(): void
    {
        $sources = WhatsAppTemplateParamMappingInferrer::infer(
            ['student_name', 'roll_number', 'check_in_time', 'date'],
            4,
        );

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'campaign.time',
            'campaign.date',
        ], $sources);
    }

    public function test_infers_attendance_template_variables(): void
    {
        $sources = WhatsAppTemplateParamMappingInferrer::infer(
            ['student_name', 'roll_number', 'check_out_time', 'date'],
            4,
        );

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'campaign.time',
            'campaign.date',
        ], $sources);
    }

    public function test_infers_marks_template_variables(): void
    {
        $sources = WhatsAppTemplateParamMappingInferrer::infer(
            ['name', 'roll_number', 'tes', 'all_subject_marks'],
            4,
        );

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'activity.test_name',
            'activity.marks_summary',
        ], $sources);
    }

    public function test_numeric_variable_labels_stay_manual(): void
    {
        $sources = WhatsAppTemplateParamMappingInferrer::infer(['1', '2', '3'], 3);

        $this->assertSame([null, null, null], $sources);
    }

    public function test_template_uses_inferred_mappings_when_param_mappings_empty(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'parent_attendance_auto_cut_agra',
            'param_count' => 4,
            'param_mappings' => [],
            'provider_meta' => [
                'body_variables' => ['student_name', 'roll_number', 'check_out_time', 'date'],
            ],
        ]);

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'campaign.time',
            'campaign.date',
        ], $template->paramSources());
    }

    public function test_template_uses_inferred_mappings_when_param_mappings_are_null_slots(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'test_marks_api',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'provider_meta' => [
                'body_variables' => ['name', 'roll_numbebr', 'tes', 'all_subject_marks'],
            ],
        ]);

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'activity.test_name',
            'activity.marks_summary',
        ], $template->paramSources());
    }

    public function test_ensure_param_mappings_persists_inferred_marks_mappings(): void
    {
        $template = WhatsAppTemplate::query()->create([
            'name' => 'test_marks_api',
            'param_count' => 4,
            'param_mappings' => [null, null, null, null],
            'provider_meta' => [
                'body_variables' => ['name', 'roll_numbebr', 'tes', 'all_subject_marks'],
            ],
        ]);

        $template->ensureParamMappings();

        $this->assertSame([
            'student.name',
            'student.enrollment_number',
            'activity.test_name',
            'activity.marks_summary',
        ], $template->fresh()->param_mappings);
    }
}

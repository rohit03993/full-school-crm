<?php

namespace Tests\Feature;

use App\Enums\WhatsAppInchatAudience;
use App\Enums\WhatsAppInchatFieldType;
use App\Models\WhatsAppInchatForm;
use App\Models\WhatsAppInchatFormField;
use App\Models\WhatsAppMarketingContact;
use App\Services\WhatsAppMarketingList;
use App\Support\WhatsAppInchatFlowJson;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppFlowPathAndMarketingTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_question_can_wait_for_one_choice(): void
    {
        $form = WhatsAppInchatForm::query()->create([
            'name' => 'Admission enquiry',
            'audience' => WhatsAppInchatAudience::EducationLead,
            'trigger_word' => 'admission',
            'button_label' => 'Open',
            'enabled' => true,
            'status' => 'draft',
        ]);
        WhatsAppInchatFormField::query()->create([
            'whatsapp_inchat_form_id' => $form->id,
            'sort' => 0,
            'field_type' => WhatsAppInchatFieldType::Choices,
            'label' => 'Course',
            'is_required' => true,
            'choices' => ['Science', 'Commerce'],
        ]);
        WhatsAppInchatFormField::query()->create([
            'whatsapp_inchat_form_id' => $form->id,
            'sort' => 1,
            'field_type' => WhatsAppInchatFieldType::ShortText,
            'label' => 'Father name',
            'is_required' => false,
            'show_when' => 'Course|c0',
        ]);

        $encoded = json_encode(WhatsAppInchatFlowJson::definition($form->fresh('fields')));

        $this->assertIsString($encoded);
        $this->assertStringContainsString('"type":"If"', $encoded);
        $this->assertStringContainsString("== 'c0'", $encoded);
    }

    public function test_a_marketing_file_skips_a_repeated_number_and_does_not_make_a_student(): void
    {
        $result = app(WhatsAppMarketingList::class)->importGrid(
            ['Name', 'Mobile', 'City'],
            [
                ['Aman', '9876501111', 'Pune'],
                ['Aman again', '919876501111', 'Pune'],
            ],
        );

        $this->assertSame(1, $result['added']);
        $this->assertSame(1, $result['skipped']);
        $this->assertSame(1, WhatsAppMarketingContact::query()->count());
        $this->assertSame('Pune', WhatsAppMarketingContact::query()->first()->extra['City']);
        $this->assertDatabaseCount('students', 0);
    }
}

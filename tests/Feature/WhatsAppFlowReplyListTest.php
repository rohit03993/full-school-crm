<?php

namespace Tests\Feature;

use App\Enums\StudentStatus;
use App\Enums\VisitStatus;
use App\Models\Student;
use App\Models\Visit;
use App\Services\WhatsAppFlowReplyList;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WhatsAppFlowReplyListTest extends TestCase
{
    use RefreshDatabase;

    public function test_replies_list_shows_a_finished_flow_and_can_filter_it(): void
    {
        $student = Student::query()->create([
            'name' => 'Riya',
            'mobile' => '9876501111',
            'status' => StudentStatus::Enquiry,
        ]);

        Visit::query()->create([
            'student_id' => $student->id,
            'visit_date' => now()->toDateString(),
            'discussion_summary' => "Admission enquiry\nYour name: Riya\nDate of birth was not collected on WhatsApp. Please update the student.",
            'remarks' => 'whatsapp_lead:wamid.1',
            'status' => VisitStatus::FollowUpRequired,
        ]);

        Visit::query()->create([
            'student_id' => $student->id,
            'visit_date' => now()->toDateString(),
            'discussion_summary' => "Walk-in notes",
            'remarks' => 'Spoke at the desk',
            'status' => VisitStatus::FollowUpRequired,
        ]);

        $list = app(WhatsAppFlowReplyList::class);

        $all = $list->rows();
        $this->assertCount(1, $all);
        $this->assertSame('Riya', $all[0]['name']);
        $this->assertSame('9876501111', $all[0]['mobile']);
        $this->assertSame('Admission enquiry', $all[0]['flow']);
        $this->assertSame('Your name: Riya', $all[0]['answers']);
        $this->assertNotNull($all[0]['profile_url']);

        $this->assertCount(1, $list->rows('Admission enquiry'));
        $this->assertCount(0, $list->rows('campus'));
        $this->assertCount(1, $list->rows('Admission enquiry', 50, 'Riya'));
        $this->assertCount(0, $list->rows('Admission enquiry', 50, '99999'));
        $this->assertSame(1, $list->count('Admission enquiry'));
    }

    public function test_turning_a_visit_form_off_keeps_the_words(): void
    {
        $this->assertTrue(\App\Support\WhatsAppCampusVisitFlowJson::enabled());

        \App\Support\WhatsAppCampusVisitFlowJson::saveCopy(['enabled' => false]);

        $this->assertFalse(\App\Support\WhatsAppCampusVisitFlowJson::enabled());
        $this->assertSame('Course', \App\Support\WhatsAppCampusVisitFlowJson::copy()['course_label']);
    }
}

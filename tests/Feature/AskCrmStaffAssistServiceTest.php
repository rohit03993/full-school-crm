<?php

namespace Tests\Feature;

use App\Services\AskCrmStaffAssistService;
use Tests\TestCase;

class AskCrmStaffAssistServiceTest extends TestCase
{
    public function test_detects_parent_whatsapp_requests(): void
    {
        $service = app(AskCrmStaffAssistService::class);

        $this->assertTrue($service->wantsParentWhatsAppCopy('ABHINAV SINGH homework — whatsapp message for parent'));
        $this->assertTrue($service->wantsParentWhatsAppCopy('fee pending for ayyush parent whatsapp'));
        $this->assertFalse($service->wantsParentWhatsAppCopy('ABHINAV SINGH homework status'));
    }
}

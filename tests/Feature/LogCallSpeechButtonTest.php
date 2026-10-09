<?php

namespace Tests\Feature;

use Tests\TestCase;

class LogCallSpeechButtonTest extends TestCase
{
    public function test_student_call_notes_box_has_the_speak_button(): void
    {
        $html = view('filament.pages.partials.log-call-modal', [
            'showLogCallModal' => true,
            'logCallContext' => 'enrolled',
            'logCallModalMode' => 'profile',
            'logCallLeadName' => 'Aarav Bindal',
            'logCallLeadPhone' => '7817882246',
            'logCallForm' => [
                'call_connected' => true,
                'call_direction' => 'outgoing',
            ],
        ])->render();

        $this->assertStringContainsString('Log student call', $html);
        $this->assertStringContainsString('data-device-speech="call-notes"', $html);
        $this->assertStringContainsString('Speak', $html);
        $this->assertStringContainsString('interimResults = true', $html);
        $this->assertStringContainsString('logCallForm.call_notes', $html);
        $this->assertStringContainsString('Save call log', $html);
        $this->assertStringContainsString('At least 10 characters required.', $html);
    }
}

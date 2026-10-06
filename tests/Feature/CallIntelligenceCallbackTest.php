<?php

namespace Tests\Feature;

use App\Enums\StudentStatus;
use App\Models\CallRecording;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CallIntelligenceCallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_callback_saves_the_summary_and_rejects_a_bad_secret(): void
    {
        config([
            'call_intelligence.callback_secret' => 'callback-secret',
        ]);

        $student = Student::query()->create([
            'name' => 'Rahul',
            'mobile' => '9876543210',
            'status' => StudentStatus::Enrolled,
        ]);

        $recording = CallRecording::query()->create([
            'public_id' => '550e8400-e29b-41d4-a716-446655440000',
            'student_id' => $student->id,
            'phone_number' => '9876543210',
            'processing_status' => 'ANALYZING',
        ]);

        $payload = [
            'call_id' => $recording->public_id,
            'status' => 'COMPLETED',
            'summary' => 'The customer asked about Class 8. The annual fee is ₹45,000.',
            'short_summary' => 'Class 8 fee is ₹45,000.',
            'transcript_text' => "Speaker 1:\nThe annual fee is ₹45,000.",
            'transcript_json' => [
                'language' => 'en',
                'segments' => [
                    ['start' => 0, 'end' => 4, 'speaker' => 'Speaker 1', 'text' => 'The annual fee is ₹45,000.'],
                ],
            ],
            'ai_analysis' => [
                'lead_status' => 'Interested',
                'interest_level' => 'High',
                'follow_up_required' => false,
                'follow_up_reason' => 'Do not call',
                'suggested_follow_up_date' => '2026-10-07',
            ],
            'error' => null,
        ];

        $raw = json_encode($payload);
        $signature = hash_hmac('sha256', $raw, 'callback-secret');

        $this->call('POST', '/api/call-intelligence/result', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer wrong',
            'HTTP_X_CALL_AI_SIGNATURE' => $signature,
        ], $raw)->assertForbidden();

        $this->call('POST', '/api/call-intelligence/result', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => 'Bearer callback-secret',
            'HTTP_X_CALL_AI_SIGNATURE' => $signature,
        ], $raw)->assertOk();

        $recording->refresh();
        $this->assertSame('COMPLETED', $recording->processing_status);
        $this->assertStringContainsString('₹45,000', (string) $recording->summary);
        $this->assertFalse($recording->follow_up_required);
        $this->assertNull($recording->suggested_follow_up_date);
    }
}

<?php

namespace App\Services;

use App\Enums\AskCrmIntent;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AskCrmGeminiService
{
    public function isEnabled(): bool
    {
        return (bool) config('ask_crm.use_ai')
            && filled(config('ask_crm.gemini_api_key'));
    }

    /**
     * Parse a staff question into intent + student name using Gemini.
     *
     * @return array{intent: AskCrmIntent, student_name: ?string}|null
     */
    public function parseQuestion(string $question): ?array
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $model = (string) config('ask_crm.gemini_model', 'gemini-2.0-flash');
        $apiKey = (string) config('ask_crm.gemini_api_key');
        $timeout = (int) config('ask_crm.gemini_timeout_seconds', 15);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $this->systemPrompt()],
                                ['text' => 'User question: '.$question],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.1,
                        'responseMimeType' => 'application/json',
                    ],
                ]);

            if (! $response->successful()) {
                Log::warning('Ask CRM Gemini request failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);

                return null;
            }

            $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

            if (! is_string($text) || trim($text) === '') {
                return null;
            }

            $decoded = json_decode(trim($text), true);

            if (! is_array($decoded)) {
                return null;
            }

            $intent = $this->mapIntent($decoded['intent'] ?? null);

            if ($intent === null) {
                return null;
            }

            $studentName = $decoded['student_name'] ?? null;
            $studentName = is_string($studentName) ? trim($studentName) : null;

            if ($studentName === '') {
                $studentName = null;
            }

            return [
                'intent' => $intent,
                'student_name' => $studentName,
            ];
        } catch (\Throwable $exception) {
            Log::warning('Ask CRM Gemini error', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    protected function systemPrompt(): string
    {
        return <<<'PROMPT'
You parse questions from school CRM staff about students. Reply with JSON only — no markdown.

Schema:
{
  "intent": one of "help", "attendance_today", "attendance_month", "fee_pending", "homework_week", "unknown",
  "student_name": string or null
}

Rules:
- "help" when the user asks what you can do or wants examples.
- "attendance_today" for today's attendance, present/absent today, punch/check-in today.
- "attendance_month" for monthly attendance percentage or "this month" attendance stats.
- "fee_pending" for fee balance, dues, pending fees, bakaya/baaki.
- "homework_week" for homework not done this week.
- Extract the student first name or full name only — never include words like attendance, fee, homework.
- Understand English and Hinglish (e.g. "aayush aaj aaya?", "fee kitni baaki hai Rahul ki").
- If no student name is mentioned, set student_name to null.
PROMPT;
    }

    protected function mapIntent(mixed $value): ?AskCrmIntent
    {
        if (! is_string($value)) {
            return null;
        }

        $value = strtolower(trim($value));

        return match ($value) {
            'help' => AskCrmIntent::Help,
            'attendance_today' => AskCrmIntent::AttendanceToday,
            'attendance_month' => AskCrmIntent::AttendanceMonth,
            'fee_pending' => AskCrmIntent::FeePending,
            'homework_week' => AskCrmIntent::HomeworkWeek,
            'unknown' => AskCrmIntent::Unknown,
            default => null,
        };
    }
}

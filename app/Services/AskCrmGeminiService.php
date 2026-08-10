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
     * Parse intent + student from a turn, using chat history and last student context.
     *
     * @param  list<array{role: string, text: string}>  $history
     * @return array{intent: AskCrmIntent, student_name: ?string, use_context_student: bool}|null
     */
    public function parseQuestion(
        string $question,
        array $history = [],
        ?int $contextStudentId = null,
        ?string $contextStudentName = null,
    ): ?array {
        if (! $this->isEnabled()) {
            return null;
        }

        $prompt = $this->systemPromptForParse($contextStudentId, $contextStudentName)
            ."\n\nConversation so far:\n".$this->formatHistory($history)
            ."\n\nLatest user message: ".$question;

        $decoded = $this->requestJson($prompt);

        if ($decoded === null) {
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
            'use_context_student' => (bool) ($decoded['use_context_student'] ?? false),
        ];
    }

    /**
     * Compose a natural reply strictly from CRM snapshot data.
     *
     * @param  list<array{role: string, text: string}>  $history
     * @param  array<string, mixed>  $snapshot
     */
    public function composeReply(string $question, array $history, array $snapshot): ?string
    {
        if (! $this->isEnabled()) {
            return null;
        }

        $dataJson = json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        if (! is_string($dataJson)) {
            return null;
        }

        $prompt = <<<'PROMPT'
You are Ask CRM — a friendly assistant for school staff inside their CRM admin panel.

Rules:
- Answer ONLY using the CRM data JSON below. Never invent numbers, names, or statuses.
- Be conversational and clear. Short paragraphs are fine.
- For follow-ups ("has he done?", "what does that mean?"), use the student in the CRM data and the conversation history.
- If homework today has no marks yet, say homework is not marked yet for today — do not say "looking good" unless data supports it.
- If fee data says can_view=false, say the user lacks permission to view fees.
- Use **bold** sparingly for key facts (status, amounts).
- Do not mention JSON, APIs, or that you are an AI.
PROMPT;

        $prompt .= "\n\nCRM data:\n".$dataJson;
        $prompt .= "\n\nConversation so far:\n".$this->formatHistory($history);
        $prompt .= "\n\nLatest user message: ".$question;
        $prompt .= "\n\nWrite the assistant reply (plain text, no JSON):";

        return $this->requestText($prompt, temperature: 0.35);
    }

    /**
     * @param  list<array{role: string, text: string}>  $history
     */
    protected function formatHistory(array $history): string
    {
        $lines = [];

        foreach (array_slice($history, -12) as $item) {
            $role = ($item['role'] ?? '') === 'user' ? 'User' : 'Assistant';
            $text = trim((string) ($item['text'] ?? ''));

            if ($text !== '') {
                $lines[] = $role.': '.$text;
            }
        }

        return $lines === [] ? '(none)' : implode("\n", $lines);
    }

    protected function systemPromptForParse(?int $contextStudentId, ?string $contextStudentName): string
    {
        $context = $contextStudentId
            ? 'Last discussed student: '.($contextStudentName ?? 'ID '.$contextStudentId).' (id '.$contextStudentId.').'
            : 'No student in context yet.';

        return <<<PROMPT
You parse staff questions for a school CRM chatbot. Reply with JSON only.

{$context}

Schema:
{
  "intent": "help" | "attendance_today" | "attendance_month" | "fee_pending" | "homework_week" | "unknown",
  "student_name": string or null,
  "use_context_student": boolean
}

Rules:
- Set use_context_student=true when the user refers to he/she/his/her/the same student, or asks a follow-up without naming a new student.
- For follow-ups like "has he done or not?", "what is good?", keep the same student from context and pick the best intent from the conversation (often homework_week).
- Extract student_name only when a NEW name is mentioned. Never treat words like good, he, done, or not as a name.
- Understand English and Hinglish.
PROMPT;
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function requestJson(string $prompt): ?array
    {
        $text = $this->requestText($prompt, temperature: 0.1, jsonMode: true);

        if ($text === null) {
            return null;
        }

        $decoded = json_decode(trim($text), true);

        return is_array($decoded) ? $decoded : null;
    }

    protected function requestText(string $prompt, float $temperature = 0.2, bool $jsonMode = false): ?string
    {
        $model = (string) config('ask_crm.gemini_model', 'gemini-2.0-flash');
        $apiKey = (string) config('ask_crm.gemini_api_key');
        $timeout = (int) config('ask_crm.gemini_timeout_seconds', 15);

        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        $generationConfig = ['temperature' => $temperature];

        if ($jsonMode) {
            $generationConfig['responseMimeType'] = 'application/json';
        }

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => $prompt],
                            ],
                        ],
                    ],
                    'generationConfig' => $generationConfig,
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

            return trim($text);
        } catch (\Throwable $exception) {
            Log::warning('Ask CRM Gemini error', [
                'message' => $exception->getMessage(),
            ]);

            return null;
        }
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

<?php

namespace App\Ai\Homework;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class GeminiHomeworkTextImprover implements HomeworkTextImprover
{
    public function providerName(): string
    {
        return 'gemini';
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.gemini.key'));
    }

    public function improve(string $title, string $description, array $context = []): HomeworkImprovement
    {
        $model = $this->model();
        $apiKey = (string) config('ai.gemini.key');
        $timeout = max(5, (int) config('ai.gemini.timeout', 20));
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/'.$model.':generateContent';

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withQueryParameters(['key' => $apiKey])
                ->post($url, [
                    'systemInstruction' => [
                        'parts' => [
                            ['text' => HomeworkImprovePrompt::system()],
                        ],
                    ],
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => HomeworkImprovePrompt::user($title, $description, $context)],
                            ],
                        ],
                    ],
                    'generationConfig' => [
                        'temperature' => 0.4,
                        'responseMimeType' => 'application/json',
                        'responseSchema' => [
                            'type' => 'OBJECT',
                            'properties' => [
                                'title' => ['type' => 'STRING'],
                                'description' => ['type' => 'STRING'],
                            ],
                            'required' => ['title', 'description'],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Homework AI Gemini request failed', [
                'model' => $model,
                'message' => $this->redact($exception->getMessage()),
            ]);

            throw new \RuntimeException('Gemini request failed.', 0, $exception);
        }

        if (! $response->successful()) {
            Log::warning('Homework AI Gemini request failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $this->redact($response->body()),
            ]);

            throw new \RuntimeException('Gemini returned status '.$response->status().'.');
        }

        $parsed = HomeworkJson::decode($this->responseText($response->json()));

        if ($parsed === null) {
            Log::warning('Homework AI Gemini returned unreadable JSON', [
                'model' => $model,
            ]);

            throw new \RuntimeException('Gemini returned unreadable JSON.');
        }

        return new HomeworkImprovement($parsed['title'], $parsed['description']);
    }

    private function model(): string
    {
        $model = (string) config('ai.gemini.model', 'gemini-3.5-flash-lite');

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
            throw new \RuntimeException('Homework AI model name is not valid.');
        }

        return $model;
    }

    private function responseText(mixed $json): string
    {
        $parts = data_get($json, 'candidates.0.content.parts', []);

        if (! is_array($parts)) {
            return '';
        }

        $text = '';

        foreach ($parts as $part) {
            if (! is_array($part) || ! empty($part['thought'])) {
                continue;
            }

            if (isset($part['text']) && is_string($part['text'])) {
                $text .= $part['text'];
            }
        }

        return $text;
    }

    private function redact(string $text): string
    {
        $key = (string) config('ai.gemini.key');

        if ($key !== '') {
            $text = str_replace($key, '***', $text);
        }

        $text = preg_replace('/key=[^&\s]+/i', 'key=***', $text) ?? $text;

        return mb_substr($text, 0, 300);
    }
}

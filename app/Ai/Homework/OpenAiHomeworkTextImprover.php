<?php

namespace App\Ai\Homework;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

final class OpenAiHomeworkTextImprover implements HomeworkTextImprover
{
    public function providerName(): string
    {
        return 'openai';
    }

    public function isConfigured(): bool
    {
        return filled(config('ai.openai.key'));
    }

    public function improve(string $title, string $description): HomeworkImprovement
    {
        $model = $this->model();
        $apiKey = (string) config('ai.openai.key');
        $timeout = max(5, (int) config('ai.openai.timeout', 20));
        $baseUrl = (string) config('ai.openai.base_url', 'https://api.openai.com/v1');

        try {
            $response = Http::timeout($timeout)
                ->acceptJson()
                ->withToken($apiKey)
                ->post($baseUrl.'/chat/completions', [
                    'model' => $model,
                    'temperature' => 0.2,
                    'messages' => [
                        ['role' => 'system', 'content' => HomeworkImprovePrompt::system()],
                        ['role' => 'user', 'content' => HomeworkImprovePrompt::user($title, $description)],
                    ],
                    'response_format' => [
                        'type' => 'json_schema',
                        'json_schema' => [
                            'name' => 'homework_improvement',
                            'strict' => true,
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'title' => ['type' => 'string'],
                                    'description' => ['type' => 'string'],
                                ],
                                'required' => ['title', 'description'],
                                'additionalProperties' => false,
                            ],
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('Homework AI OpenAI request failed', [
                'model' => $model,
                'message' => $this->redact($exception->getMessage()),
            ]);

            throw new \RuntimeException('OpenAI request failed.', 0, $exception);
        }

        if (! $response->successful()) {
            Log::warning('Homework AI OpenAI request failed', [
                'model' => $model,
                'status' => $response->status(),
                'body' => $this->redact($response->body()),
            ]);

            throw new \RuntimeException('OpenAI returned status '.$response->status().'.');
        }

        $content = data_get($response->json(), 'choices.0.message.content');
        $parsed = is_string($content) ? HomeworkJson::decode($content) : null;

        if ($parsed === null) {
            Log::warning('Homework AI OpenAI returned unreadable JSON', [
                'model' => $model,
            ]);

            throw new \RuntimeException('OpenAI returned unreadable JSON.');
        }

        return new HomeworkImprovement($parsed['title'], $parsed['description']);
    }

    private function model(): string
    {
        $model = (string) config('ai.openai.model', 'gpt-4.1-mini');

        if (! preg_match('/^[A-Za-z0-9._-]+$/', $model)) {
            throw new \RuntimeException('Homework AI model name is not valid.');
        }

        return $model;
    }

    private function redact(string $text): string
    {
        $key = (string) config('ai.openai.key');

        if ($key !== '') {
            $text = str_replace($key, '***', $text);
        }

        $text = preg_replace('/Bearer\s+\S+/i', 'Bearer ***', $text) ?? $text;

        return mb_substr($text, 0, 300);
    }
}

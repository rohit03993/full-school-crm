<?php

namespace App\Ai\Homework;

final class HomeworkJson
{
    /**
     * @return array{title: string, description: string}|null
     */
    public static function decode(string $text): ?array
    {
        $text = trim($text);

        if (str_starts_with($text, '```')) {
            $text = preg_replace('/^```(?:json)?\s*/i', '', $text) ?? $text;
            $text = preg_replace('/\s*```$/', '', $text) ?? $text;
            $text = trim($text);
        }

        $decoded = json_decode($text, true);

        if (! is_array($decoded)) {
            return null;
        }

        $title = $decoded['title'] ?? '';
        $description = $decoded['description'] ?? '';

        if (! is_string($title) || ! is_string($description)) {
            return null;
        }

        $title = trim($title);
        $description = trim($description);

        if ($title === '' && $description === '') {
            return null;
        }

        return [
            'title' => mb_substr($title, 0, 255),
            'description' => mb_substr($description, 0, 4000),
        ];
    }
}

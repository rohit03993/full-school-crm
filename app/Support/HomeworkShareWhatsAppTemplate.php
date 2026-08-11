<?php

namespace App\Support;

/**
 * Canonical Meta Utility template for batch homework share (public /h/ link, no login).
 * Create under WhatsApp → Templates, then pick it when uploading homework.
 */
final class HomeworkShareWhatsAppTemplate
{
    public const NAME = 'homework_api';

    /** @var list<string> */
    public const ALIASES = [
        'homework_api',
        'homework_update',
        'homework_share',
    ];

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent,
Homework for {{1}} (Roll: {{2}})

Title: {{3}}

Open homework (view/download, no login):
{{4}}
TXT;

    /**
     * @return array<int, array{label: string, example: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Student name',
                'example' => 'Rohit Sharma',
            ],
            2 => [
                'label' => 'Roll number',
                'example' => '12-A-042',
            ],
            3 => [
                'label' => 'Homework title',
                'example' => 'Chapter 5 exercises',
            ],
            4 => [
                'label' => 'Public homework link',
                'example' => 'https://example.com/h/samplePublicHomeworkToken123456',
            ],
        ];
    }

    /**
     * @return list<array{index: int, label: string, example: string}>
     */
    public static function sampleRows(): array
    {
        $rows = [];

        foreach (self::variables() as $index => $variable) {
            $rows[] = [
                'index' => $index,
                'label' => $variable['label'],
                'example' => $variable['example'],
            ];
        }

        return $rows;
    }

    public static function looksLikeName(string $name): bool
    {
        $normalized = MetaWhatsAppTemplateBuilder::normalizeName($name);

        if ($normalized === '') {
            return false;
        }

        if (in_array($normalized, self::ALIASES, true)) {
            return true;
        }

        // Explicit share names only — not homework_not_done or arbitrary homework_*.
        return str_starts_with($normalized, 'homework_share')
            || str_starts_with($normalized, 'homework_api');
    }
}

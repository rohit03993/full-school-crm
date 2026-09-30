<?php

namespace App\Support;

/**
 * Dedicated Meta Utility template for Homework Check → Not Done parent WhatsApp.
 * Separate from share-homework and attendance punch templates.
 */
final class HomeworkNotDoneWhatsAppTemplate
{
    public const NAME = 'homework_not_done';

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Dear Parent,

Your child {{1}} of Class {{2}} has not completed the homework for {{3}}.

Homework: {{4}}
Date: {{5}}
Open homework:
{{6}}

Please ensure the homework is completed.

— {{7}}
Thank you.
TXT;

    /**
     * @return array<int, array{label: string, example: string, crm_source: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => 'Student name',
                'example' => 'Riya Sharma',
                'crm_source' => 'student.name',
            ],
            2 => [
                'label' => 'Class / section',
                'example' => '10 · Section A',
                'crm_source' => 'homework.class_section',
            ],
            3 => [
                'label' => 'Subject',
                'example' => 'Mathematics',
                'crm_source' => 'homework.subject',
            ],
            4 => [
                'label' => 'Homework title',
                'example' => 'Chapter 5 – Q1 to Q10',
                'crm_source' => 'homework.topic',
            ],
            5 => [
                'label' => 'Homework date',
                'example' => '30 Sep 2026',
                'crm_source' => 'homework.date',
            ],
            6 => [
                'label' => 'That student’s own homework link',
                'example' => 'https://example.com/h/sampleToken',
                'crm_source' => 'homework.link',
            ],
            7 => [
                'label' => 'Institute name',
                'example' => 'B.D.M. Kanya Degree College',
                'crm_source' => 'institute.name',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public static function mappingSources(): array
    {
        return array_values(array_map(
            static fn (array $variable): string => (string) $variable['crm_source'],
            self::variables(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function exampleValues(): array
    {
        return array_values(array_map(
            static fn (array $variable): string => (string) $variable['example'],
            self::variables(),
        ));
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
                'source' => (string) ($variable['crm_source'] ?? ''),
            ];
        }

        return $rows;
    }

    public static function looksLikeName(string $name): bool
    {
        return WhatsAppTemplateParamMappingInferrer::looksLikeHomeworkNotDoneTemplateName($name);
    }
}

<?php

namespace App\Support;

use App\Enums\WhatsAppInchatFieldType;
use App\Models\WhatsAppInchatForm;
use App\Models\WhatsAppInchatFormField;

class WhatsAppInchatFlowJson
{
    public const SCREEN_ID = 'FORM';

    /**
     * @return array<string, mixed>
     */
    public static function definition(WhatsAppInchatForm $form): array
    {
        $children = [];

        foreach ($form->fields as $field) {
            $component = self::component($field);

            if ($component === null) {
                continue;
            }

            $branch = self::branch($form, $field);
            $children[] = $branch === null ? $component : [
                'type' => 'If',
                'condition' => '${form.'.$branch['key']."} == '".$branch['choice']."'",
                'then' => [$component],
                'else' => [],
            ];
        }

        $children[] = [
            'type' => 'Footer',
            'label' => 'Finish',
            'on-click-action' => [
                'name' => 'complete',
                'payload' => self::payload($form),
            ],
        ];

        return [
            'version' => '7.0',
            'routing_model' => [
                self::SCREEN_ID => [],
            ],
            'screens' => [
                [
                    'id' => self::SCREEN_ID,
                    'title' => mb_substr(trim($form->name), 0, 30),
                    'terminal' => true,
                    'success' => true,
                    'data' => new \stdClass,
                    'layout' => [
                        'type' => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'Form',
                                'name' => 'form',
                                'children' => $children,
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function encoded(WhatsAppInchatForm $form): string
    {
        return json_encode(self::definition($form), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, string>
     */
    protected static function payload(WhatsAppInchatForm $form): array
    {
        $payload = [
            'flow_token' => (string) $form->flow_token,
        ];

        foreach ($form->fields as $field) {
            $payload[$field->answerKey()] = '${form.'.$field->answerKey().'}';
        }

        return $payload;
    }

    /**
     * @return array{key: string, choice: string}|null
     */
    protected static function branch(WhatsAppInchatForm $form, WhatsAppInchatFormField $field): ?array
    {
        $raw = trim((string) $field->show_when);

        if ($raw === '' || ! str_contains($raw, '|')) {
            return null;
        }

        [$label, $choice] = explode('|', $raw, 2);
        $label = trim($label);
        $choice = trim($choice);

        if ($label === '' || ! preg_match('/^c\d+$/', $choice)) {
            return null;
        }

        $parent = $form->fields->first(function (WhatsAppInchatFormField $item) use ($label, $field): bool {
            return $item->id !== $field->id
                && $item->field_type === WhatsAppInchatFieldType::Choices
                && trim($item->label) === $label;
        });

        if (! $parent) {
            return null;
        }

        return [
            'key' => $parent->answerKey(),
            'choice' => $choice,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    protected static function component(WhatsAppInchatFormField $field): ?array
    {
        $label = mb_substr(trim($field->label), 0, 30);

        if ($label === '') {
            return null;
        }

        $base = [
            'name' => $field->answerKey(),
            'label' => $label,
            'required' => (bool) $field->is_required,
        ];

        return match ($field->field_type) {
            WhatsAppInchatFieldType::ShortText => $base + [
                'type' => 'TextInput',
                'input-type' => 'text',
            ],
            WhatsAppInchatFieldType::Note => $base + [
                'type' => 'TextArea',
            ],
            WhatsAppInchatFieldType::Date => $base + [
                'type' => 'DatePicker',
            ],
            WhatsAppInchatFieldType::Choices => $base + [
                'type' => 'Dropdown',
                'data-source' => self::choiceOptions($field),
            ],
            WhatsAppInchatFieldType::CourseList => $base + [
                'type' => 'Dropdown',
                'data-source' => WhatsAppCampusVisitFlowJson::courseOptions(),
            ],
        };
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    protected static function choiceOptions(WhatsAppInchatFormField $field): array
    {
        $options = [];

        foreach (array_values($field->choices ?? []) as $index => $choice) {
            $title = mb_substr(trim((string) $choice), 0, 30);

            if ($title === '') {
                continue;
            }

            $options[] = [
                'id' => 'c'.$index,
                'title' => $title,
            ];
        }

        if ($options === []) {
            return [
                ['id' => 'c0', 'title' => 'Yes'],
            ];
        }

        return $options;
    }
}

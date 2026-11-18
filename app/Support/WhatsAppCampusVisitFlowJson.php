<?php

namespace App\Support;

use App\Models\Course;
use App\Models\Setting;

class WhatsAppCampusVisitFlowJson
{
    public const FLOW_NAME = 'campus_visit';

    public const SCREEN_ID = 'VISIT';

    public const FLOW_TOKEN = 'campus_visit';

    public const COPY_SETTING = 'whatsapp.campus_visit_copy';

    /**
     * Words shown on the visit form. Course, date, and note stay, because a visit needs them.
     *
     * @return array{enabled: bool, course_label: string, date_label: string, note_label: string, button_label: string, note_required: bool}
     */
    public static function copy(): array
    {
        $saved = Setting::getValue(self::COPY_SETTING, []);
        $saved = is_array($saved) ? $saved : [];

        return [
            'enabled' => array_key_exists('enabled', $saved) ? (bool) $saved['enabled'] : true,
            'course_label' => self::label($saved['course_label'] ?? null, 'Course'),
            'date_label' => self::label($saved['date_label'] ?? null, 'Visit date'),
            'note_label' => self::label($saved['note_label'] ?? null, 'Note'),
            'button_label' => self::label($saved['button_label'] ?? null, 'Finish', 20),
            'note_required' => (bool) ($saved['note_required'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $copy
     */
    public static function saveCopy(array $copy): void
    {
        $current = self::copy();

        Setting::setValue(self::COPY_SETTING, [
            'enabled' => array_key_exists('enabled', $copy) ? (bool) $copy['enabled'] : $current['enabled'],
            'course_label' => self::label($copy['course_label'] ?? null, $current['course_label']),
            'date_label' => self::label($copy['date_label'] ?? null, $current['date_label']),
            'note_label' => self::label($copy['note_label'] ?? null, $current['note_label']),
            'button_label' => self::label($copy['button_label'] ?? null, $current['button_label'], 20),
            'note_required' => array_key_exists('note_required', $copy) ? (bool) $copy['note_required'] : $current['note_required'],
        ], 'whatsapp');
    }

    public static function enabled(): bool
    {
        return self::copy()['enabled'];
    }

    /**
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
        $copy = self::copy();

        return [
            'version' => '7.0',
            'routing_model' => [
                self::SCREEN_ID => [],
            ],
            'screens' => [
                [
                    'id' => self::SCREEN_ID,
                    'title' => 'Campus visit',
                    'terminal' => true,
                    'success' => true,
                    'data' => new \stdClass,
                    'layout' => [
                        'type' => 'SingleColumnLayout',
                        'children' => [
                            [
                                'type' => 'Form',
                                'name' => 'visit_form',
                                'children' => [
                                    [
                                        'type' => 'Dropdown',
                                        'name' => 'course',
                                        'label' => $copy['course_label'],
                                        'required' => true,
                                        'data-source' => self::courseOptions(),
                                    ],
                                    [
                                        'type' => 'DatePicker',
                                        'name' => 'visit_date',
                                        'label' => $copy['date_label'],
                                        'required' => true,
                                    ],
                                    [
                                        'type' => 'TextArea',
                                        'name' => 'note',
                                        'label' => $copy['note_label'],
                                        'required' => $copy['note_required'],
                                    ],
                                    [
                                        'type' => 'Footer',
                                        'label' => $copy['button_label'],
                                        'on-click-action' => [
                                            'name' => 'complete',
                                            'payload' => [
                                                'flow_token' => self::FLOW_TOKEN,
                                                'course' => '${form.course}',
                                                'visit_date' => '${form.visit_date}',
                                                'note' => '${form.note}',
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    public static function encoded(): string
    {
        return json_encode(self::definition(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    /**
     * @return list<array{id: string, title: string}>
     */
    public static function courseOptions(): array
    {
        $options = InstituteProfile::adminCoursesQuery(
            Course::query()->active()->orderBy('name'),
        )
            ->limit(200)
            ->get(['id', 'name'])
            ->map(function (Course $course): array {
                $title = trim((string) $course->name);

                if ($title === '') {
                    $title = 'Course '.$course->id;
                }

                return [
                    'id' => (string) $course->id,
                    'title' => mb_substr($title, 0, 30),
                ];
            })
            ->filter(fn (array $option): bool => $option['id'] !== '')
            ->values()
            ->all();

        if ($options === []) {
            return [
                ['id' => 'general', 'title' => 'General'],
            ];
        }

        return $options;
    }

    protected static function label(mixed $value, string $fallback, int $max = 30): string
    {
        $label = trim((string) $value);

        if ($label === '') {
            $label = $fallback;
        }

        return mb_substr($label, 0, $max);
    }
}

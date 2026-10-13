<?php

namespace App\Support;

use App\Models\Course;

class WhatsAppCampusVisitFlowJson
{
    public const FLOW_NAME = 'campus_visit';

    public const SCREEN_ID = 'VISIT';

    public const FLOW_TOKEN = 'campus_visit';

    /**
     * @return array<string, mixed>
     */
    public static function definition(): array
    {
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
                                        'label' => 'Course',
                                        'required' => true,
                                        'data-source' => self::courseOptions(),
                                    ],
                                    [
                                        'type' => 'DatePicker',
                                        'name' => 'visit_date',
                                        'label' => 'Visit date',
                                        'required' => true,
                                    ],
                                    [
                                        'type' => 'TextArea',
                                        'name' => 'note',
                                        'label' => 'Note',
                                        'required' => false,
                                    ],
                                    [
                                        'type' => 'Footer',
                                        'label' => 'Finish',
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
}

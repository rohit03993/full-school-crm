<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'name' => 'Exam',
                'plural_name' => 'Exams',
                'slug' => 'exam',
                'icon' => 'heroicon-o-academic-cap',
                'description' => 'Unit tests, term exams, and formal assessments.',
                'sort_order' => 10,
                'field_schema' => [
                    ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                    ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
                ],
            ],
            [
                'name' => 'Mock Test',
                'plural_name' => 'Mock Tests',
                'slug' => 'mock_test',
                'icon' => 'heroicon-o-clipboard-document-check',
                'description' => 'Practice tests for competitive exam preparation.',
                'sort_order' => 20,
                'field_schema' => [
                    ['key' => 'paper', 'label' => 'Paper / Topic', 'type' => 'text'],
                    ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
                ],
            ],
            [
                'name' => 'Workshop',
                'plural_name' => 'Workshops',
                'slug' => 'workshop',
                'icon' => 'heroicon-o-presentation-chart-bar',
                'description' => 'Skill-building sessions and hands-on workshops.',
                'sort_order' => 30,
                'field_schema' => [
                    ['key' => 'topic', 'label' => 'Topic', 'type' => 'text'],
                    ['key' => 'facilitator', 'label' => 'Facilitator', 'type' => 'text'],
                ],
            ],
            [
                'name' => 'Event',
                'plural_name' => 'Events',
                'slug' => 'event',
                'icon' => 'heroicon-o-calendar-days',
                'description' => 'Seminars, orientations, and institute events.',
                'sort_order' => 40,
                'field_schema' => [
                    ['key' => 'venue', 'label' => 'Venue', 'type' => 'text'],
                ],
            ],
        ];

        foreach ($types as $type) {
            ActivityType::query()->updateOrCreate(
                ['slug' => $type['slug']],
                [
                    ...$type,
                    'is_enabled' => true,
                ],
            );
        }
    }
}

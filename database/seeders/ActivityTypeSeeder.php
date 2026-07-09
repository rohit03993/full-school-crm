<?php

namespace Database\Seeders;

use App\Models\ActivityType;
use Illuminate\Database\Seeder;

class ActivityTypeSeeder extends Seeder
{
    public function run(): void
    {
        ActivityType::query()->updateOrCreate(
            ['slug' => 'exam'],
            [
                'name' => 'Exam',
                'plural_name' => 'Exams',
                'icon' => 'heroicon-o-academic-cap',
                'description' => 'Unit tests, term exams, and formal assessments.',
                'sort_order' => 10,
                'field_schema' => [
                    ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                    ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
                ],
                'is_enabled' => true,
            ],
        );

        ActivityType::query()
            ->whereIn('slug', ['mock_test', 'workshop', 'event'])
            ->update(['is_enabled' => false]);
    }
}

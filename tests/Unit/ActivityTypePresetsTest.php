<?php

namespace Tests\Unit;

use App\Support\ActivityTypePresets;
use Tests\TestCase;

class ActivityTypePresetsTest extends TestCase
{
    public function test_ensure_marks_fields_adds_subject_and_max_marks(): void
    {
        $fields = ActivityTypePresets::ensureMarksFields([]);

        $keys = collect($fields)->pluck('key')->all();

        $this->assertContains('subject', $keys);
        $this->assertContains('max_marks', $keys);
    }

    public function test_strip_marks_fields_removes_scoring_keys(): void
    {
        $fields = ActivityTypePresets::stripMarksFields([
            ['key' => 'topic', 'label' => 'Topic', 'type' => 'text'],
            ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
        ]);

        $this->assertSame(['topic'], collect($fields)->pluck('key')->all());
    }
}

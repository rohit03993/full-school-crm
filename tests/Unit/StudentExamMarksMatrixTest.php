<?php

namespace Tests\Unit;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentExamMarksMatrixTest extends TestCase
{
    use RefreshDatabase;

    public function test_groups_subject_marks_into_one_row_per_test(): void
    {
        $type = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        $batchId = 1;
        $userId = 1;

        $sessions = collect([
            ['Mathematics', 45],
            ['Physics', 33],
            ['Chemistry', 39],
        ])->map(function (array $pair) use ($type, $batchId, $userId): ActivitySession {
            [$subject, $marks] = $pair;

            return ActivitySession::query()->make([
                'activity_type_id' => $type->id,
                'title' => "Unit Test — June 2026 — {$subject}",
                'session_date' => '2026-06-16',
                'batch_id' => $batchId,
                'metadata' => [
                    'test_key' => 'unit-test-june-2026-2026-06-16',
                    'test_name' => 'Unit Test — June 2026',
                    'subject' => $subject,
                    'max_marks' => 50,
                ],
                'created_by_user_id' => $userId,
            ]);
        });

        $records = $sessions->map(function (ActivitySession $session, int $index): object {
            return (object) [
                'marks_obtained' => [45, 33, 39][$index],
                'grade' => null,
                'attendable' => $session,
            ];
        });

        $matrix = StudentExamMarksMatrix::fromRecords($records);

        $this->assertSame(['Chemistry', 'Mathematics', 'Physics'], $matrix['subjects']);
        $this->assertCount(1, $matrix['rows']);
        $this->assertSame('Unit Test — June 2026', $matrix['rows'][0]['label']);
        $this->assertSame('45 / 50', $matrix['rows'][0]['scores']['Mathematics']['display']);
        $this->assertSame('33 / 50', $matrix['rows'][0]['scores']['Physics']['display']);
        $this->assertSame('39 / 50', $matrix['rows'][0]['scores']['Chemistry']['display']);
    }

    public function test_shows_multiple_tests_as_separate_rows(): void
    {
        $type = ActivityType::query()->create([
            'name' => 'Exam',
            'field_schema' => [
                ['key' => 'subject', 'label' => 'Subject', 'type' => 'text'],
                ['key' => 'max_marks', 'label' => 'Max Marks', 'type' => 'number'],
            ],
            'is_enabled' => true,
        ]);

        $june = ActivitySession::query()->make([
            'activity_type_id' => $type->id,
            'title' => 'Unit Test — June 2026 — Maths',
            'session_date' => '2026-06-16',
            'batch_id' => 1,
            'metadata' => [
                'test_key' => 'unit-june',
                'test_name' => 'Unit Test — June 2026',
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => 1,
        ]);

        $july = ActivitySession::query()->make([
            'activity_type_id' => $type->id,
            'title' => 'Unit Test — July 2026 — Maths',
            'session_date' => '2026-07-16',
            'batch_id' => 1,
            'metadata' => [
                'test_key' => 'unit-july',
                'test_name' => 'Unit Test — July 2026',
                'subject' => 'Mathematics',
                'max_marks' => 50,
            ],
            'created_by_user_id' => 1,
        ]);

        $records = collect([
            (object) ['marks_obtained' => 40.0, 'grade' => null, 'attendable' => $june],
            (object) ['marks_obtained' => 44.0, 'grade' => null, 'attendable' => $july],
        ]);

        $matrix = StudentExamMarksMatrix::fromRecords($records);

        $this->assertCount(2, $matrix['rows']);
        $this->assertSame('Unit Test — July 2026', $matrix['rows'][0]['label']);
        $this->assertSame('Unit Test — June 2026', $matrix['rows'][1]['label']);
    }
}

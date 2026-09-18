<?php

namespace App\Support;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\BatchStudent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class ExamTestGroupMatrix
{
    public const RECENT_MONTHS = 24;
    /**
     * @return array{
     *     subjects: list<string>,
     *     rows: list<array{
     *         group_key: string,
     *         test_key: ?string,
     *         label: string,
     *         date: ?\Illuminate\Support\Carbon,
     *         batch: ?string,
     *         batch_id: ?int,
     *         type: ?string,
     *         activity_type_id: ?int,
     *         tracks_marks: bool,
     *         session_id: ?int,
     *         present_count: int,
     *         student_count: int,
     *         subject_names: list<string>,
     *         subjects: array<string, array{marks_count: int, session_id: int, max_marks: mixed, present_count: int}>
     *     }>
     * }
     */
    public static function build(?int $batchId = null, ?int $activityTypeId = null): array
    {
        $query = ActivitySession::query()
            ->with(['activityType', 'batch'])
            ->withCount([
                'activityAttendances as marks_count' => fn (Builder $query): Builder => $query->whereNotNull('marks_obtained'),
                'activityAttendances as present_count' => fn (Builder $query): Builder => $query->where('is_present', true),
            ])
            ->where('session_date', '>=', Carbon::now()->subMonths(self::RECENT_MONTHS)->toDateString())
            ->orderByDesc('session_date')
            ->orderByDesc('id');

        if ($batchId) {
            $query->where('batch_id', $batchId);
        }

        if ($activityTypeId) {
            $query->where('activity_type_id', $activityTypeId);
        }

        $scoringTypeIds = ActivityType::scoringTypeIds();

        if ($scoringTypeIds !== []) {
            $query->whereIn('activity_type_id', $scoringTypeIds);
        }

        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];
        /** @var array<string, true> $subjectSet */
        $subjectSet = [];

        foreach ($query->get() as $session) {
            $subject = StudentExamMarksMatrix::subjectForSession($session);
            $subjectSet[$subject] = true;
            $groupKey = StudentExamMarksMatrix::groupKeyForSession($session);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'group_key' => $groupKey,
                    'test_key' => filled($session->metadataValue('test_key'))
                        ? (string) $session->metadataValue('test_key')
                        : null,
                    'label' => StudentExamMarksMatrix::testLabelForSession($session),
                    'date' => $session->session_date,
                    'batch' => $session->batch?->name,
                    'batch_id' => $session->batch_id,
                    'type' => $session->activityType?->name,
                    'activity_type_id' => $session->activity_type_id,
                    'tracks_marks' => (bool) $session->activityType?->supportsScoring(),
                    'session_id' => $session->id,
                    'present_count' => (int) $session->present_count,
                    'sort_date' => $session->session_date?->format('Y-m-d') ?? '',
                    'subjects' => [],
                ];
            }

            $existingSubject = $grouped[$groupKey]['subjects'][$subject] ?? null;
            $marksCount = (int) $session->marks_count;
            $presentCount = (int) $session->present_count;

            if ($existingSubject !== null) {
                $marksCount = max((int) $existingSubject['marks_count'], $marksCount);
                $presentCount = max((int) $existingSubject['present_count'], $presentCount);
            }

            $useThisSession = $existingSubject === null
                || $marksCount > (int) $existingSubject['marks_count']
                || ! filled($existingSubject['max_marks']);

            $grouped[$groupKey]['subjects'][$subject] = [
                'marks_count' => $marksCount,
                'session_id' => $useThisSession ? $session->id : $existingSubject['session_id'],
                'max_marks' => $useThisSession
                    ? $session->metadataValue('max_marks')
                    : $existingSubject['max_marks'],
                'present_count' => $presentCount,
            ];

            if (! $grouped[$groupKey]['tracks_marks']) {
                $grouped[$groupKey]['present_count'] = max(
                    (int) $grouped[$groupKey]['present_count'],
                    (int) $session->present_count,
                );
            }
        }

        $subjects = array_keys($subjectSet);
        usort($subjects, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        $rows = collect($grouped)
            ->sortByDesc('sort_date')
            ->values()
            ->map(function (array $row): array {
                $subjectCells = $row['subjects'];
                uksort($subjectCells, fn (string $a, string $b): int => strnatcasecmp($a, $b));

                $row['subjects'] = $subjectCells;
                $row['subject_names'] = array_keys($subjectCells);
                $row['student_count'] = (int) collect($subjectCells)->max('marks_count');
                $row['session_id'] ??= collect($subjectCells)
                    ->pluck('session_id')
                    ->filter()
                    ->first();

                return $row;
            })
            ->all();

        return [
            'subjects' => $subjects,
            'rows' => $rows,
        ];
    }

    /**
     * Batch mark sheet: students as rows, subjects as columns for one test group.
     *
     * @return array{
     *     test_label: string,
     *     batch: ?string,
     *     date: ?string,
     *     subjects: list<string>,
     *     subject_max: array<string, float|null>,
     *     rows: list<array{
     *         student_id: int,
     *         roll_number: string,
     *         student_name: string,
     *         scores: array<string, string>,
     *         cells: array<string, array{marks: ?float, max: ?float, display: string}>
     *     }>
     * }|null
     */
    public static function markSheetForGroup(string $groupKey): ?array
    {
        $query = ActivitySession::query()
            ->with(['batch', 'activityAttendances.student.activeEnrollment']);

        if (! str_contains($groupKey, '|')) {
            $query->where('metadata->test_key', $groupKey);
        } else {
            $parts = explode('|', $groupKey, 4);

            if (count($parts) === 4) {
                [$activityTypeId, $batchId, $date, $testLabel] = $parts;
                $query
                    ->where('activity_type_id', (int) $activityTypeId)
                    ->where('batch_id', (int) $batchId)
                    ->whereDate('session_date', $date)
                    ->where('metadata->test_name', $testLabel);
            }
        }

        $sessions = $query->get()->values();

        if ($sessions->isEmpty()) {
            return null;
        }

        $first = $sessions->first();
        $subjects = [];
        /** @var array<string, float|null> $subjectMax */
        $subjectMax = [];
        /** @var array<int, array{student_id: int, roll_number: string, student_name: string, scores: array<string, string>, cells: array<string, array{marks: ?float, max: ?float, display: string}>}> $studentRows */
        $studentRows = [];

        if ($first->batch_id) {
            $batchStudents = BatchStudent::query()
                ->where('batch_id', $first->batch_id)
                ->where('is_active', true)
                ->with(['student.activeEnrollment'])
                ->get();

            foreach ($batchStudents as $batchStudent) {
                $student = $batchStudent->student;

                if (! $student) {
                    continue;
                }

                $studentRows[$student->id] = [
                    'student_id' => $student->id,
                    'roll_number' => (string) ($student->activeEnrollment?->enrollment_number ?? "\u{2014}"),
                    'student_name' => (string) $student->name,
                    'scores' => [],
                    'cells' => [],
                ];
            }
        }

        foreach ($sessions as $session) {
            $subject = StudentExamMarksMatrix::subjectForSession($session);
            $subjects[$subject] = true;
            $sessionMax = filled($session->metadataValue('max_marks'))
                ? (float) $session->metadataValue('max_marks')
                : null;

            if (! array_key_exists($subject, $subjectMax) || ($subjectMax[$subject] === null && $sessionMax !== null)) {
                $subjectMax[$subject] = $sessionMax;
            }

            foreach ($session->activityAttendances as $attendance) {
                $student = $attendance->student;

                if (! $student) {
                    continue;
                }

                $studentId = $student->id;

                if (! isset($studentRows[$studentId])) {
                    $studentRows[$studentId] = [
                        'student_id' => $studentId,
                        'roll_number' => (string) ($student->activeEnrollment?->enrollment_number ?? "\u{2014}"),
                        'student_name' => (string) $student->name,
                        'scores' => [],
                        'cells' => [],
                    ];
                }

                $maxMarks = $sessionMax;
                $marks = $attendance->marks_obtained !== null ? (float) $attendance->marks_obtained : null;
                $display = StudentExamMarksMatrix::formatMarks(
                    $marks,
                    $maxMarks,
                    $attendance->grade,
                    'Absent',
                );
                $existingCell = $studentRows[$studentId]['cells'][$subject] ?? null;

                if ($existingCell === null || (($existingCell['marks'] ?? null) === null && $marks !== null)) {
                    $studentRows[$studentId]['cells'][$subject] = [
                        'marks' => $marks,
                        'max' => $maxMarks,
                        'display' => $display,
                    ];
                    $studentRows[$studentId]['scores'][$subject] = $display;
                }
            }
        }

        $subjectList = array_keys($subjects);
        usort($subjectList, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        $rows = collect($studentRows)
            ->sortBy('roll_number')
            ->values()
            ->map(function (array $row) use ($subjectList, $subjectMax): array {
                foreach ($subjectList as $subject) {
                    $row['cells'][$subject] ??= [
                        'marks' => null,
                        'max' => $subjectMax[$subject] ?? null,
                        'display' => 'Absent',
                    ];
                    $row['scores'][$subject] = $row['cells'][$subject]['display'];
                }

                return $row;
            })
            ->all();

        return [
            'test_label' => StudentExamMarksMatrix::testLabelForSession($first),
            'batch' => $first->batch?->name,
            'batch_id' => $first->batch_id,
            'activity_type_id' => $first->activity_type_id,
            'date' => $first->session_date?->toDateString(),
            'subjects' => $subjectList,
            'subject_max' => $subjectMax,
            'rows' => $rows,
        ];
    }
}

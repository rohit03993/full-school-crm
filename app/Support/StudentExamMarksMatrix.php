<?php

namespace App\Support;

use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class StudentExamMarksMatrix
{
    /**
     * @param  Collection<int, ActivityAttendance>  $records
     * @return array{
     *     subjects: list<string>,
     *     rows: list<array{
     *         label: string,
     *         date: ?\Illuminate\Support\Carbon,
     *         batch: ?string,
     *         scores: array<string, array{marks: ?float, max: ?float, display: string}>
     *     }>
     * }
     */
    public static function fromRecords(Collection $records): array
    {
        /** @var array<string, array<string, mixed>> $grouped */
        $grouped = [];
        /** @var array<string, true> $subjectSet */
        $subjectSet = [];

        foreach ($records as $record) {
            $session = $record->attendable;

            if (! $session instanceof ActivitySession) {
                continue;
            }

            $subject = self::subjectForSession($session);
            $subjectSet[$subject] = true;
            $groupKey = self::groupKeyForSession($session);
            $testLabel = self::testLabelForSession($session);

            if (! isset($grouped[$groupKey])) {
                $grouped[$groupKey] = [
                    'label' => $testLabel,
                    'date' => $session->session_date,
                    'batch' => $session->batch?->name,
                    'sort_date' => $session->session_date?->format('Y-m-d') ?? '',
                    'scores' => [],
                ];
            }

            $maxMarks = filled($session->metadataValue('max_marks'))
                ? (float) $session->metadataValue('max_marks')
                : null;

            $marks = $record->marks_obtained !== null ? (float) $record->marks_obtained : null;

            $grouped[$groupKey]['scores'][$subject] = [
                'marks' => $marks,
                'max' => $maxMarks,
                'display' => self::formatMarks($marks, $maxMarks, $record->grade),
            ];
        }

        $subjects = array_keys($subjectSet);
        usort($subjects, fn (string $a, string $b): int => strnatcasecmp($a, $b));

        $rows = collect($grouped)
            ->sortByDesc('sort_date')
            ->values()
            ->map(function (array $row) use ($subjects): array {
                $scores = [];

                foreach ($subjects as $subject) {
                    $scores[$subject] = $row['scores'][$subject] ?? [
                        'marks' => null,
                        'max' => null,
                        'display' => '—',
                    ];
                }

                return [
                    'label' => $row['label'],
                    'date' => $row['date'],
                    'batch' => $row['batch'],
                    'scores' => $scores,
                ];
            })
            ->all();

        return [
            'subjects' => $subjects,
            'rows' => $rows,
        ];
    }

    public static function subjectForSession(ActivitySession $session): string
    {
        $subject = trim((string) ($session->metadataValue('subject') ?? ''));

        if ($subject !== '') {
            return $subject;
        }

        $title = trim($session->title);

        if (str_contains($title, ' — ')) {
            return trim(Str::afterLast($title, ' — '));
        }

        return 'Subject';
    }

    public static function testLabelForSession(ActivitySession $session): string
    {
        $testName = trim((string) ($session->metadataValue('test_name') ?? ''));

        if ($testName !== '') {
            return $testName;
        }

        $title = trim($session->title);
        $subject = self::subjectForSession($session);
        $suffix = ' — '.$subject;

        if ($subject !== 'Subject' && str_ends_with($title, $suffix)) {
            return trim(Str::beforeLast($title, $suffix));
        }

        return $title;
    }

    public static function groupKeyForSession(ActivitySession $session): string
    {
        $testKey = trim((string) ($session->metadataValue('test_key') ?? ''));

        if ($testKey !== '') {
            return $testKey;
        }

        return implode('|', [
            (string) $session->activity_type_id,
            (string) $session->batch_id,
            $session->session_date?->format('Y-m-d') ?? '',
            self::testLabelForSession($session),
        ]);
    }

    public static function formatMarks(?float $marks, ?float $maxMarks, ?string $grade): string
    {
        if ($marks !== null) {
            $formatted = rtrim(rtrim(number_format($marks, 2), '0'), '.');

            if ($maxMarks !== null && $maxMarks > 0) {
                $maxFormatted = rtrim(rtrim(number_format($maxMarks, 2), '0'), '.');

                return "{$formatted} / {$maxFormatted}";
            }

            return $formatted;
        }

        return filled($grade) ? $grade : '—';
    }
}

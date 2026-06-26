<?php

namespace App\Services;

use App\Models\ActivitySession;
use App\Models\ActivityType;
use App\Models\BatchStudent;
use App\Models\Enrollment;
use App\Models\User;
use App\Support\ExamSubjectCatalog;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ActivityMarksBulkImportService
{
    public function __construct(
        protected ActivityAttendanceService $attendance,
        protected AuditService $audit,
    ) {}

    public function buildTestKey(string $testName, string $sessionDate): string
    {
        return Str::slug($testName).'-'.Carbon::parse($sessionDate)->format('Y-m-d');
    }

    /**
     * @param  list<string|null>  $headers
     * @param  list<list<string|null>>  $rows
     * @param  array{roll_column: int|null, subject_columns: list<int>}  $mapping
     * @param  array<int, float|int|string>  $subjectMaxMarksByColumn
     * @return array{
     *     rows: list<array<string, mixed>>,
     *     subjects: list<string>,
     *     subject_max_marks: array<string, float>,
     *     batches: list<array{id: int, name: string, count: int}>,
     *     ready_count: int,
     *     error_count: int
     * }
     */
    public function buildPreview(
        array $headers,
        array $rows,
        array $mapping,
        ?int $academicSessionId = null,
        ?int $batchId = null,
        float $defaultMaxMarks = 100,
        array $subjectMaxMarksByColumn = [],
    ): array {
        $subjectLabels = $this->subjectLabels($headers, $mapping['subject_columns'] ?? []);
        $subjectMaxMarks = $this->resolvedSubjectMaxMarks(
            $headers,
            $mapping['subject_columns'] ?? [],
            $subjectMaxMarksByColumn,
            $defaultMaxMarks,
        );
        $subjects = array_values(array_unique($subjectLabels));
        $seenRolls = [];
        $previewRows = [];
        $batchCounts = [];
        $readyCount = 0;
        $errorCount = 0;

        foreach ($rows as $index => $row) {
            $rowNumber = $index + 2;
            $roll = $this->cell($row, $mapping['roll_column'] ?? null);
            $errors = [];

            if ($roll === '') {
                $errors[] = 'Roll number is required.';
            }

            $normalizedRoll = strtoupper(trim($roll));

            if ($normalizedRoll !== '' && isset($seenRolls[$normalizedRoll])) {
                $errors[] = "Duplicate roll number in file (also on row {$seenRolls[$normalizedRoll]}).";
            } elseif ($normalizedRoll !== '') {
                $seenRolls[$normalizedRoll] = $rowNumber;
            }

            $enrollment = null;
            $batchStudent = null;

            if ($errors === [] && $normalizedRoll !== '') {
                $enrollment = $this->findEnrollmentByRoll($normalizedRoll, $academicSessionId);

                if (! $enrollment) {
                    $errors[] = 'No active enrollment found for this roll number.';
                } else {
                    $batchStudent = BatchStudent::query()
                        ->where('student_id', $enrollment->student_id)
                        ->where('is_active', true)
                        ->with('batch')
                        ->first();

                    if (! $batchStudent) {
                        $errors[] = 'Student is not assigned to an active batch.';
                    } elseif ($batchId !== null && (int) $batchStudent->batch_id !== $batchId) {
                        $errors[] = 'Student belongs to a different batch than the filter you selected.';
                    }
                }
            }

            $subjectMarks = [];
            $subjectErrors = [];

            foreach ($mapping['subject_columns'] ?? [] as $columnIndex) {
                $subject = $subjectLabels[$columnIndex] ?? 'Subject';
                $maxMarks = $subjectMaxMarks[$subject] ?? $defaultMaxMarks;
                $rawMark = $this->cell($row, $columnIndex);

                if ($rawMark === '') {
                    continue;
                }

                if (! is_numeric($rawMark)) {
                    $subjectErrors[] = "{$subject}: mark must be numeric.";

                    continue;
                }

                $mark = (float) $rawMark;

                if ($mark < 0) {
                    $subjectErrors[] = "{$subject}: mark cannot be negative.";

                    continue;
                }

                if ($mark > $maxMarks) {
                    $subjectErrors[] = "{$subject}: mark exceeds max marks ({$maxMarks}).";
                }

                $subjectMarks[$subject] = $mark;
            }

            if ($subjectMarks === [] && $errors === []) {
                $errors[] = 'No subject marks found on this row.';
            }

            $errors = array_merge($errors, $subjectErrors);
            $status = $errors === [] ? 'ready' : 'error';

            if ($status === 'ready') {
                $readyCount++;
                $batchKey = (int) $batchStudent->batch_id;
                $batchCounts[$batchKey] = ($batchCounts[$batchKey] ?? 0) + 1;
            } else {
                $errorCount++;
            }

            $previewRows[] = [
                'row_number' => $rowNumber,
                'roll_number' => $normalizedRoll,
                'student_id' => $enrollment?->student_id,
                'student_name' => $enrollment?->student?->name,
                'batch_id' => $batchStudent?->batch_id,
                'batch_name' => $batchStudent?->batch?->name,
                'subject_marks' => $subjectMarks,
                'status' => $status,
                'errors' => $errors,
            ];
        }

        $batches = collect($batchCounts)
            ->map(function (int $count, int $id): array {
                $batch = \App\Models\Batch::query()->find($id);

                return [
                    'id' => $id,
                    'name' => $batch?->name ?? 'Batch #'.$id,
                    'count' => $count,
                ];
            })
            ->values()
            ->all();

        return [
            'rows' => $previewRows,
            'subjects' => $subjects,
            'subject_max_marks' => $subjectMaxMarks,
            'batches' => $batches,
            'ready_count' => $readyCount,
            'error_count' => $errorCount,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $previewRows
     * @return array{
     *     test_key: string,
     *     sessions_created: int,
     *     sessions_updated: int,
     *     marks_saved: int,
     *     students: int,
     *     batches: int,
     *     errors: list<array{row: int, message: string}>
     * }
     */
    public function import(
        User $staff,
        ActivityType $activityType,
        string $testName,
        string $sessionDate,
        float $defaultMaxMarks,
        array $previewRows,
        array $subjectMaxMarks = [],
    ): array {
        if (! $activityType->supportsScoring()) {
            throw new \InvalidArgumentException('Selected activity type does not support marks.');
        }

        $testKey = $this->buildTestKey($testName, $sessionDate);
        $readyRows = collect($previewRows)->where('status', 'ready')->values();

        /** @var array<int, array<string, array<int, float>>> $batchSubjectScores batch => subject => student => mark */
        $batchSubjectScores = [];

        foreach ($readyRows as $row) {
            $batchId = (int) $row['batch_id'];
            $studentId = (int) $row['student_id'];

            foreach ($row['subject_marks'] as $subject => $mark) {
                $batchSubjectScores[$batchId][$subject][$studentId] = (float) $mark;
            }
        }

        $sessionsCreated = 0;
        $sessionsUpdated = 0;
        $marksSaved = 0;
        $errors = [];

        foreach ($batchSubjectScores as $batchId => $subjects) {
            foreach ($subjects as $subject => $studentScores) {
                try {
                    [$session, $created] = $this->findOrCreateSession(
                        $activityType,
                        $batchId,
                        $testName,
                        $sessionDate,
                        $testKey,
                        $subject,
                        (float) ($subjectMaxMarks[$subject] ?? $defaultMaxMarks),
                        $staff,
                    );

                    if ($created) {
                        $sessionsCreated++;
                    } else {
                        $sessionsUpdated++;
                    }

                    $marksSaved += $this->attendance->importStudentScores($session, $studentScores, $staff);
                } catch (\Throwable $exception) {
                    $errors[] = [
                        'row' => 0,
                        'message' => "{$subject} ({$sessionDate}): ".$exception->getMessage(),
                    ];
                }
            }
        }

        if ($marksSaved > 0) {
            $this->audit->log(
                'activity_marks_imported',
                null,
                null,
                [
                    'test_key' => $testKey,
                    'test_name' => $testName,
                    'session_date' => $sessionDate,
                    'marks_saved' => $marksSaved,
                    'sessions_created' => $sessionsCreated,
                    'sessions_updated' => $sessionsUpdated,
                ],
                user: $staff,
            );
        }

        return [
            'test_key' => $testKey,
            'test_name' => $testName,
            'session_date' => $sessionDate,
            'sessions_created' => $sessionsCreated,
            'sessions_updated' => $sessionsUpdated,
            'marks_saved' => $marksSaved,
            'students' => $readyRows->pluck('student_id')->unique()->count(),
            'batches' => count($batchSubjectScores),
            'errors' => $errors,
        ];
    }

    /**
     * @param  list<int>  $subjectColumnIndexes
     * @return array<int, string>
     */
    protected function subjectLabels(array $headers, array $subjectColumnIndexes): array
    {
        $labels = [];

        foreach ($subjectColumnIndexes as $index) {
            $labels[$index] = ExamSubjectCatalog::resolveLabel($headers[$index] ?? null);
        }

        return $labels;
    }

    /**
     * @param  list<int>  $subjectColumnIndexes
     * @param  array<int, float|int|string>  $subjectMaxMarksByColumn
     * @return array<string, float>
     */
    protected function resolvedSubjectMaxMarks(
        array $headers,
        array $subjectColumnIndexes,
        array $subjectMaxMarksByColumn,
        float $defaultMaxMarks,
    ): array {
        $resolved = [];

        foreach ($subjectColumnIndexes as $columnIndex) {
            $label = ExamSubjectCatalog::resolveLabel($headers[$columnIndex] ?? null);
            $rawMax = $subjectMaxMarksByColumn[$columnIndex]
                ?? $subjectMaxMarksByColumn[(string) $columnIndex]
                ?? null;
            $resolved[$label] = $rawMax !== null && $rawMax !== ''
                ? (float) $rawMax
                : ExamSubjectCatalog::defaultMaxForHeader($headers[$columnIndex] ?? null, $defaultMaxMarks);
        }

        return $resolved;
    }

    /**
     * @param  list<string|null>  $row
     */
    protected function cell(array $row, ?int $index): string
    {
        if ($index === null) {
            return '';
        }

        return trim((string) ($row[$index] ?? ''));
    }

    protected function findEnrollmentByRoll(string $normalizedRoll, ?int $academicSessionId = null): ?Enrollment
    {
        return Enrollment::query()
            ->where('is_active', true)
            ->whereRaw('UPPER(TRIM(enrollment_number)) = ?', [$normalizedRoll])
            ->when(
                $academicSessionId,
                fn ($query) => $query->where('academic_session_id', $academicSessionId),
            )
            ->with('student')
            ->first();
    }

    /**
     * @return array{0: ActivitySession, 1: bool} session and whether it was created
     */
    protected function findOrCreateSession(
        ActivityType $activityType,
        int $batchId,
        string $testName,
        string $sessionDate,
        string $testKey,
        string $subject,
        float $defaultMaxMarks,
        User $staff,
    ): array {
        $existing = ActivitySession::query()
            ->where('activity_type_id', $activityType->id)
            ->where('batch_id', $batchId)
            ->whereDate('session_date', $sessionDate)
            ->where('metadata->test_key', $testKey)
            ->where('metadata->subject', $subject)
            ->first();

        if ($existing) {
            return [$existing, false];
        }

        $session = ActivitySession::query()->create([
            'activity_type_id' => $activityType->id,
            'title' => "{$testName} — {$subject}",
            'session_date' => $sessionDate,
            'batch_id' => $batchId,
            'metadata' => [
                'test_key' => $testKey,
                'test_name' => $testName,
                'subject' => $subject,
                'max_marks' => $defaultMaxMarks,
            ],
            'created_by_user_id' => $staff->id,
        ]);

        return [$session, true];
    }
}

<?php

namespace App\Services;

use App\Enums\ResultDeclarationStatus;
use App\Models\ActivityAttendance;
use App\Models\ActivitySession;
use App\Models\BatchStudent;
use App\Models\ResultDeclaration;
use App\Models\Student;
use App\Models\StudentMarksheet;
use App\Models\User;
use App\Support\ExamTestGroupMatrix;
use App\Support\MarksheetDivision;
use App\Support\PublishedResultsGate;
use App\Support\StudentExamMarksMatrix;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ResultDeclarationService
{
    public function __construct(
        protected ActivityAttendanceService $attendance,
        protected MarksheetPdfService $marksheets,
        protected AuditService $audit,
        protected AttendanceService $classAttendance,
    ) {}

    public function findForGroupKey(string $groupKey): ?ResultDeclaration
    {
        return ResultDeclaration::query()->where('group_key', $groupKey)->first();
    }

    public function findOrCreateForGroupKey(string $groupKey): ResultDeclaration
    {
        $existing = $this->findForGroupKey($groupKey);

        if ($existing) {
            return $existing;
        }

        $markSheet = ExamTestGroupMatrix::markSheetForGroup($groupKey);

        if (! $markSheet) {
            throw ValidationException::withMessages([
                'group_key' => 'No marks found for this test.',
            ]);
        }

        return ResultDeclaration::query()->create([
            'group_key' => $groupKey,
            'test_name' => (string) $markSheet['test_label'],
            'session_date' => $markSheet['date'],
            'batch_id' => (int) $markSheet['batch_id'],
            'activity_type_id' => (int) $markSheet['activity_type_id'],
            'status' => ResultDeclarationStatus::Draft,
        ]);
    }

    public function savePrincipalRemarks(string $groupKey, ?string $remarks): ResultDeclaration
    {
        $declaration = $this->findOrCreateForGroupKey($groupKey);
        $declaration->update([
            'remarks' => filled($remarks) ? trim($remarks) : null,
        ]);

        return $declaration->fresh();
    }

    public function publish(string $groupKey, User $staff, ?string $declarationDate = null): ResultDeclaration
    {
        $declaration = $this->findOrCreateForGroupKey($groupKey);

        if ($declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'This result is already published.',
            ]);
        }

        app(ExamWindowService::class)->assertApprovedForPublish($groupKey);

        $markSheet = ExamTestGroupMatrix::markSheetForGroup($groupKey);

        if (! $markSheet || ($markSheet['rows'] ?? []) === []) {
            throw ValidationException::withMessages([
                'marks' => 'Enter marks for at least one student before publishing.',
            ]);
        }

        $declarationDate = $declarationDate ?: now()->toDateString();

        DB::transaction(function () use ($declaration, $groupKey, $staff, $declarationDate, $markSheet): void {
            $batchId = (int) $markSheet['batch_id'];
            $activityTypeId = (int) $markSheet['activity_type_id'];

            $students = BatchStudent::query()
                ->where('batch_id', $batchId)
                ->where('is_active', true)
                ->with('student.activeEnrollment')
                ->get()
                ->pluck('student')
                ->filter();

            $sheetIds = [];

            foreach ($students as $student) {
                if (! $student instanceof Student) {
                    continue;
                }

                $snapshot = $this->snapshotForStudent($student, $groupKey, $activityTypeId);

                if ($snapshot === null) {
                    continue;
                }

                $total = $snapshot['total'] ?? [];

                $existing = StudentMarksheet::query()
                    ->where('result_declaration_id', $declaration->id)
                    ->where('student_id', $student->id)
                    ->first();

                $sheet = StudentMarksheet::query()->updateOrCreate(
                    [
                        'result_declaration_id' => $declaration->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'marksheet_serial' => $existing?->marksheet_serial ?? $this->nextMarksheetSerial(),
                        'total_obtained' => $total['marks'] ?? null,
                        'total_max' => $total['max'] ?? null,
                        'percentage' => $total['percentage'] ?? null,
                        'division' => MarksheetDivision::fromPercentage(
                            isset($total['percentage']) ? (float) $total['percentage'] : null,
                        ),
                        'snapshot' => $snapshot,
                    ],
                );

                $sheetIds[] = $sheet->id;
            }

            $this->assignRanks($declaration->fresh(['studentMarksheets']));

            $declaration->update([
                'status' => ResultDeclarationStatus::Published,
                'declaration_date' => $declarationDate,
                'declared_by_user_id' => $staff->id,
                'declared_at' => now(),
                'marks_locked_at' => now(),
                'marks_locked_by_user_id' => $staff->id,
                'unpublished_at' => null,
                'unpublished_by_user_id' => null,
            ]);

            $this->audit->log(
                'result_published',
                $declaration,
                null,
                [
                    'group_key' => $groupKey,
                    'test_name' => $declaration->test_name,
                    'declaration_date' => $declarationDate,
                    'marks_locked' => true,
                ],
                user: $staff,
            );

            $this->audit->log(
                'marks_locked',
                $declaration,
                null,
                ['group_key' => $groupKey],
                user: $staff,
            );
        });

        return $declaration->fresh(['studentMarksheets.student']);
    }

    public function unpublish(string $groupKey, User $staff): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Only published results can be unpublished.',
            ]);
        }

        DB::transaction(function () use ($declaration, $groupKey, $staff): void {
            $oldValues = [
                'status' => $declaration->status->value,
                'declaration_date' => $declaration->declaration_date?->toDateString(),
            ];

            $declaration->update([
                'status' => ResultDeclarationStatus::Draft,
                'declared_at' => null,
                'declaration_date' => null,
                'declared_by_user_id' => null,
                'marks_locked_at' => null,
                'marks_locked_by_user_id' => null,
                'unpublished_at' => now(),
                'unpublished_by_user_id' => $staff->id,
            ]);

            $this->audit->log(
                'result_unpublished',
                $declaration,
                $oldValues,
                ['group_key' => $groupKey],
                user: $staff,
            );
        });

        return $declaration->fresh(['studentMarksheets']);
    }

    public function lockMarks(string $groupKey, User $staff): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish results before locking marks.',
            ]);
        }

        if ($declaration->marksAreLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Marks are already locked.',
            ]);
        }

        $declaration->update([
            'marks_locked_at' => now(),
            'marks_locked_by_user_id' => $staff->id,
        ]);

        $this->audit->log('marks_locked', $declaration, null, ['group_key' => $groupKey], user: $staff);

        return $declaration->fresh();
    }

    public function unlockMarks(string $groupKey, User $staff): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Only published results can be unlocked.',
            ]);
        }

        if (! $declaration->marksAreLocked()) {
            throw ValidationException::withMessages([
                'status' => 'Marks are not locked.',
            ]);
        }

        $declaration->update([
            'marks_locked_at' => null,
            'marks_locked_by_user_id' => null,
        ]);

        $this->audit->log('marks_unlocked', $declaration, null, ['group_key' => $groupKey], user: $staff);

        return $declaration->fresh();
    }

    public function issueMarksheets(string $groupKey, User $staff, ?string $issueDate = null): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish results online before issuing marksheets.',
            ]);
        }

        if ($declaration->marksheetsIssued()) {
            throw ValidationException::withMessages([
                'status' => 'Marksheets already issued. Use regenerate to create PDFs again.',
            ]);
        }

        return $this->generateMarksheetsForDeclaration(
            $declaration,
            $staff,
            $issueDate ?: now()->toDateString(),
        );
    }

    public function regenerateMarksheets(string $groupKey, User $staff, ?string $issueDate = null): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish results online before regenerating marksheets.',
            ]);
        }

        if (! $declaration->marksheetsIssued()) {
            throw ValidationException::withMessages([
                'status' => 'Issue marksheets once before regenerating PDFs.',
            ]);
        }

        return $this->generateMarksheetsForDeclaration(
            $declaration,
            $staff,
            $issueDate ?: now()->toDateString(),
        );
    }

    /**
     * @param  list<string>  $groupKeys
     * @return list<ResultDeclaration>
     */
    public function publishedDeclarationsForGroupKeys(array $groupKeys, int $batchId): array
    {
        $keys = array_values(array_unique(array_filter($groupKeys)));

        if ($keys === []) {
            throw ValidationException::withMessages([
                'group_keys' => 'Select at least one published exam.',
            ]);
        }

        $declarations = ResultDeclaration::query()
            ->whereIn('group_key', $keys)
            ->where('batch_id', $batchId)
            ->get();

        foreach ($keys as $key) {
            $declaration = $declarations->firstWhere('group_key', $key);

            if (! $declaration || ! $declaration->isPublished()) {
                throw ValidationException::withMessages([
                    'group_keys' => "Exam \"{$key}\" is not published for this batch.",
                ]);
            }
        }

        return $declarations
            ->sortBy(fn (ResultDeclaration $row): int => array_search($row->group_key, $keys, true) ?: 0)
            ->values()
            ->all();
    }

    protected function generateMarksheetsForDeclaration(
        ResultDeclaration $declaration,
        User $staff,
        string $issueDate,
    ): ResultDeclaration {
        $declaration->load(['studentMarksheets.student.activeEnrollment.course', 'batch', 'activityType']);

        DB::transaction(function () use ($declaration, $staff, $issueDate): void {
            foreach ($declaration->studentMarksheets as $marksheet) {
                $this->marksheets->generate($marksheet, $declaration, $staff);
            }

            $declaration->update([
                'marksheet_issue_date' => $issueDate,
                'marksheet_issued_by_user_id' => $staff->id,
                'marksheet_issued_at' => now(),
            ]);

            $this->audit->log(
                'marksheets_issued',
                $declaration,
                null,
                [
                    'group_key' => $declaration->group_key,
                    'issue_date' => $issueDate,
                    'count' => $declaration->studentMarksheets()->count(),
                ],
                user: $staff,
            );
        });

        return $declaration->fresh(['studentMarksheets']);
    }

    protected function assignRanks(ResultDeclaration $declaration): void
    {
        $sheets = $declaration->studentMarksheets
            ->filter(fn (StudentMarksheet $sheet): bool => $sheet->total_obtained !== null)
            ->sortByDesc(fn (StudentMarksheet $sheet): float => (float) $sheet->total_obtained)
            ->values();

        $rank = 0;
        $position = 0;
        $previousTotal = null;

        foreach ($sheets as $sheet) {
            $position++;
            $total = (float) $sheet->total_obtained;

            if ($previousTotal === null || $total < $previousTotal) {
                $rank = $position;
            }

            $previousTotal = $total;
            $snapshot = $sheet->snapshot ?? [];
            $snapshot['rank'] = $rank;

            $sheet->update([
                'rank' => $rank,
                'snapshot' => $snapshot,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    protected function snapshotForStudent(Student $student, string $groupKey, int $activityTypeId): ?array
    {
        $records = $this->attendance
            ->presentRecordsForStudent($student, $activityTypeId)
            ->filter(function (ActivityAttendance $record) use ($groupKey): bool {
                $session = $record->attendable;

                return $session instanceof ActivitySession
                    && StudentExamMarksMatrix::groupKeyForSession($session) === $groupKey;
            });

        if ($records->isEmpty()) {
            return null;
        }

        $matrix = StudentExamMarksMatrix::fromRecords($records);
        $row = $matrix['rows'][0] ?? null;

        if (! $row) {
            return null;
        }

        $subjectRemarks = [];
        foreach ($records as $record) {
            $session = $record->attendable;

            if (! $session instanceof ActivitySession) {
                continue;
            }

            $subject = (string) ($session->metadataValue('subject') ?? '');

            if ($subject !== '' && filled($record->remarks)) {
                $subjectRemarks[$subject] = (string) $record->remarks;
            }
        }

        $attendancePercentage = $this->classAttendance->percentageForStudent($student);

        return [
            'subjects' => $matrix['subjects'],
            'row' => $row,
            'total' => $row['total'] ?? [],
            'attendance_percentage' => $attendancePercentage,
            'subject_remarks' => $subjectRemarks,
        ];
    }

    public function nextMarksheetSerial(): int
    {
        return (int) DB::transaction(function (): int {
            $row = DB::table('marksheet_serial_sequences')
                ->where('id', 1)
                ->lockForUpdate()
                ->first();

            $next = ((int) ($row->last_value ?? 0)) + 1;

            DB::table('marksheet_serial_sequences')
                ->where('id', 1)
                ->update(['last_value' => $next]);

            return $next;
        });
    }

    /**
     * @return array<string, mixed>
     */
    public static function statusMetaForGroupKey(string $groupKey): array
    {
        if (! Schema::hasTable('result_declarations')) {
            return [
                'status' => 'none',
                'label' => 'Not published',
                'color' => 'gray',
            ];
        }

        $declaration = ResultDeclaration::query()->where('group_key', $groupKey)->first();

        if (! $declaration) {
            return [
                'status' => 'none',
                'label' => 'Not published',
                'color' => 'gray',
            ];
        }

        if ($declaration->marksheetsIssued()) {
            return [
                'status' => 'issued',
                'label' => $declaration->marksAreLocked() ? 'Marksheet issued · locked' : 'Marksheet issued',
                'color' => 'info',
                'declaration' => $declaration,
                'marks_locked' => $declaration->marksAreLocked(),
            ];
        }

        if ($declaration->isPublished()) {
            return [
                'status' => 'published',
                'label' => $declaration->marksAreLocked() ? 'Published · marks locked' : 'Published online',
                'color' => 'success',
                'declaration' => $declaration,
                'marks_locked' => $declaration->marksAreLocked(),
            ];
        }

        return [
            'status' => 'draft',
            'label' => 'Draft',
            'color' => 'warning',
            'declaration' => $declaration,
            'marks_locked' => false,
        ];
    }
}

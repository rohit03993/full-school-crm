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

    public function publish(string $groupKey, User $staff, ?string $declarationDate = null): ResultDeclaration
    {
        $declaration = $this->findOrCreateForGroupKey($groupKey);

        if ($declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'This result is already published.',
            ]);
        }

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

            foreach ($students as $student) {
                if (! $student instanceof Student) {
                    continue;
                }

                $snapshot = $this->snapshotForStudent($student, $groupKey, $activityTypeId);

                if ($snapshot === null) {
                    continue;
                }

                $total = $snapshot['total'] ?? [];

                StudentMarksheet::query()->updateOrCreate(
                    [
                        'result_declaration_id' => $declaration->id,
                        'student_id' => $student->id,
                    ],
                    [
                        'marksheet_serial' => $this->nextMarksheetSerial(),
                        'total_obtained' => $total['marks'] ?? null,
                        'total_max' => $total['max'] ?? null,
                        'percentage' => $total['percentage'] ?? null,
                        'division' => MarksheetDivision::fromPercentage(
                            isset($total['percentage']) ? (float) $total['percentage'] : null,
                        ),
                        'snapshot' => $snapshot,
                    ],
                );
            }

            $declaration->update([
                'status' => ResultDeclarationStatus::Published,
                'declaration_date' => $declarationDate,
                'declared_by_user_id' => $staff->id,
                'declared_at' => now(),
            ]);

            $this->audit->log(
                'result_published',
                $declaration,
                null,
                [
                    'group_key' => $groupKey,
                    'test_name' => $declaration->test_name,
                    'declaration_date' => $declarationDate,
                ],
                user: $staff,
            );
        });

        return $declaration->fresh(['studentMarksheets.student']);
    }

    public function issueMarksheets(string $groupKey, User $staff, ?string $issueDate = null): ResultDeclaration
    {
        $declaration = $this->findForGroupKey($groupKey);

        if (! $declaration || ! $declaration->isPublished()) {
            throw ValidationException::withMessages([
                'status' => 'Publish results online before issuing marksheets.',
            ]);
        }

        $issueDate = $issueDate ?: now()->toDateString();

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

        return [
            'subjects' => $matrix['subjects'],
            'row' => $row,
            'total' => $row['total'] ?? [],
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
     * @return array<string, ResultDeclarationStatus|string|null>
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
                'label' => 'Marksheet issued',
                'color' => 'info',
                'declaration' => $declaration,
            ];
        }

        if ($declaration->isPublished()) {
            return [
                'status' => 'published',
                'label' => 'Published online',
                'color' => 'success',
                'declaration' => $declaration,
            ];
        }

        return [
            'status' => 'draft',
            'label' => 'Draft',
            'color' => 'warning',
            'declaration' => $declaration,
        ];
    }
}

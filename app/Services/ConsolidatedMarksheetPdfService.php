<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchStudent;
use App\Models\ResultDeclaration;
use App\Models\Student;
use App\Models\User;
use App\Support\InstituteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ConsolidatedMarksheetPdfService
{
    public const DISK = 'local';

    public function __construct(
        protected ResultDeclarationService $declarations,
        protected StorageCleanupService $storage,
    ) {}

    /**
     * @param  list<string>  $groupKeys
     */
    public function generateForStudent(
        Student $student,
        array $groupKeys,
        int $batchId,
        ?string $issueDate = null,
    ): string {
        $declarationList = $this->declarations->publishedDeclarationsForGroupKeys($groupKeys, $batchId);
        $issueDate = $issueDate ?: now()->toDateString();
        $template = InstituteSettings::forMarksheets();

        $student->loadMissing('activeEnrollment.course');
        $enrollment = $student->activeEnrollment;
        $batch = Batch::query()->findOrFail($batchId);

        $examSections = [];

        foreach ($declarationList as $declaration) {
            $sheet = $declaration->studentMarksheets()
                ->where('student_id', $student->id)
                ->first();

            if (! $sheet) {
                continue;
            }

            $snapshot = $sheet->snapshot ?? [];
            $row = $snapshot['row'] ?? [];

            $examSections[] = [
                'declaration' => $declaration,
                'marksheet' => $sheet,
                'test_label' => $declaration->test_name,
                'session_date' => $declaration->session_date,
                'subjects' => $snapshot['subjects'] ?? [],
                'scores' => $row['scores'] ?? [],
                'total' => $row['total'] ?? [],
                'rank' => $sheet->rank ?? ($snapshot['rank'] ?? null),
                'percentage' => $sheet->percentage,
                'division' => $sheet->division,
                'principal_remarks' => $declaration->remarks,
            ];
        }

        if ($examSections === []) {
            throw ValidationException::withMessages([
                'student' => 'No published marks found for this student across the selected exams.',
            ]);
        }

        $hash = Str::slug(implode('-', $groupKeys));
        $relativePath = "marksheets/consolidated/{$student->id}/{$batchId}-{$hash}.pdf";

        $this->storage->replaceStoredFile(null, $relativePath);

        $pdf = Pdf::loadView('pdf.consolidated-exam-marksheet', [
            'template' => $template,
            'student' => $student,
            'enrollment' => $enrollment,
            'course' => $enrollment?->course,
            'batch' => $batch,
            'examSections' => $examSections,
            'issueDate' => $issueDate,
            'attendancePercentage' => $examSections[0]['marksheet']->snapshot['attendance_percentage'] ?? null,
        ])->setPaper('a4', 'portrait');

        Storage::disk(self::DISK)->put($relativePath, $pdf->output());

        return $relativePath;
    }

    /**
     * @param  list<string>  $groupKeys
     * @return array<int, array{student_id: int, student_name: string, roll_number: string, pdf_path: string}>
     */
    public function generateForBatch(
        Batch $batch,
        array $groupKeys,
        User $staff,
        ?string $issueDate = null,
    ): array {
        $this->declarations->publishedDeclarationsForGroupKeys($groupKeys, $batch->id);

        $students = BatchStudent::query()
            ->where('batch_id', $batch->id)
            ->where('is_active', true)
            ->with(['student.activeEnrollment'])
            ->get()
            ->pluck('student')
            ->filter();

        $generated = [];

        foreach ($students as $student) {
            if (! $student instanceof Student) {
                continue;
            }

            try {
                $path = $this->generateForStudent($student, $groupKeys, $batch->id, $issueDate);
                $generated[] = [
                    'student_id' => $student->id,
                    'student_name' => $student->name,
                    'roll_number' => (string) ($student->activeEnrollment?->enrollment_number ?? '—'),
                    'pdf_path' => $path,
                ];
            } catch (ValidationException) {
                continue;
            }
        }

        if ($generated === []) {
            throw ValidationException::withMessages([
                'batch' => 'No students had marks for all selected exams.',
            ]);
        }

        return $generated;
    }
}

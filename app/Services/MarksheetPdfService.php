<?php

namespace App\Services;

use App\Models\ResultDeclaration;
use App\Models\StudentMarksheet;
use App\Models\User;
use App\Support\InstituteSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;

class MarksheetPdfService
{
    public const DISK = 'local';

    public function __construct(
        protected StorageCleanupService $storage,
    ) {}

    public function generate(StudentMarksheet $marksheet, ResultDeclaration $declaration, ?User $staff = null): StudentMarksheet
    {
        $marksheet->loadMissing([
            'student.activeEnrollment.course',
            'resultDeclaration.batch',
            'resultDeclaration.activityType',
        ]);

        $student = $marksheet->student;
        $enrollment = $student?->activeEnrollment;
        $relativePath = "marksheets/{$student->id}/{$declaration->id}-{$marksheet->id}.pdf";

        $this->storage->replaceStoredFile($marksheet->pdf_path, $relativePath);

        $template = InstituteSettings::forMarksheets();
        $snapshot = $marksheet->snapshot ?? [];
        $row = $snapshot['row'] ?? [];
        $subjects = $snapshot['subjects'] ?? [];
        $scores = $row['scores'] ?? [];

        $pdf = Pdf::loadView('pdf.exam-marksheet', [
            'template' => $template,
            'marksheet' => $marksheet,
            'declaration' => $declaration,
            'student' => $student,
            'enrollment' => $enrollment,
            'course' => $enrollment?->course,
            'batch' => $declaration->batch,
            'testLabel' => $declaration->test_name,
            'subjects' => $subjects,
            'scores' => $scores,
            'total' => $row['total'] ?? [],
            'rank' => $marksheet->rank ?? ($snapshot['rank'] ?? null),
            'attendancePercentage' => $snapshot['attendance_percentage'] ?? null,
            'subjectRemarks' => $snapshot['subject_remarks'] ?? [],
            'principalRemarks' => $declaration->remarks,
            'declarationDate' => $declaration->declaration_date,
            'issueDate' => $declaration->marksheet_issue_date ?? now()->toDateString(),
        ])->setPaper('a4', 'portrait');

        Storage::disk(self::DISK)->put($relativePath, $pdf->output());

        $marksheet->update(['pdf_path' => $relativePath]);

        return $marksheet->fresh();
    }
}

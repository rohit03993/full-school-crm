<?php

namespace App\Services;

use App\Enums\CertificateType;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\StudentCertificate;
use App\Models\User;
use App\Support\ClassSectionLabel;
use App\Support\InstituteSettings;
use App\Support\StudentLabels;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CertificateService
{
    public const DISK = 'local';

    public function __construct(
        protected AuditService $audit,
        protected StorageCleanupService $storage,
    ) {}

    /**
     * @param  array{remarks?: string|null, issued_on?: string|null}  $options
     */
    public function issue(
        Student $student,
        CertificateType $type,
        User $staff,
        array $options = [],
    ): StudentCertificate {
        $student->loadMissing([
            'activeEnrollment.course',
            'activeBatchStudent.batch.academicSession',
        ]);

        $enrollment = $student->activeEnrollment;

        if ($enrollment === null) {
            throw ValidationException::withMessages([
                'student_id' => 'Certificates can only be issued for enrolled students.',
            ]);
        }

        return DB::transaction(function () use ($student, $type, $staff, $options, $enrollment): StudentCertificate {
            $serial = ((int) StudentCertificate::query()->lockForUpdate()->max('serial')) + 1;
            $issuedOn = filled($options['issued_on'] ?? null)
                ? \Carbon\Carbon::parse($options['issued_on'])->toDateString()
                : now()->toDateString();

            $prefix = InstituteSettings::numberPrefix();
            $serialNumber = sprintf('%s-CERT-%s-%06d', $prefix, now()->format('Y'), $serial);

            $certificate = StudentCertificate::query()->create([
                'student_id' => $student->id,
                'enrollment_id' => $enrollment->id,
                'type' => $type,
                'serial_number' => $serialNumber,
                'serial' => $serial,
                'issued_on' => $issuedOn,
                'issued_by_user_id' => $staff->id,
                'remarks' => filled($options['remarks'] ?? null) ? trim((string) $options['remarks']) : null,
                'snapshot' => $this->buildSnapshot($student, $enrollment, $type),
            ]);

            $this->generatePdf($certificate);

            $this->audit->log(
                action: 'Certificate Issued',
                auditable: $certificate,
                newValues: [
                    'type' => $type->value,
                    'serial_number' => $serialNumber,
                    'student_id' => $student->id,
                ],
                user: $staff,
            );

            return $certificate->fresh(['student', 'enrollment.course', 'issuedBy']);
        });
    }

    public function regeneratePdf(StudentCertificate $certificate, ?User $staff = null): StudentCertificate
    {
        $this->generatePdf($certificate);

        $this->audit->log(
            action: 'Certificate PDF Regenerated',
            auditable: $certificate,
            newValues: ['pdf_path' => $certificate->pdf_path],
            user: $staff,
        );

        return $certificate->fresh(['student', 'enrollment.course', 'issuedBy']);
    }

    protected function generatePdf(StudentCertificate $certificate): void
    {
        $certificate->loadMissing([
            'student.activeBatchStudent.batch.academicSession',
            'enrollment.course',
            'issuedBy',
        ]);

        $student = $certificate->student;
        $enrollment = $certificate->enrollment;
        $snapshot = $certificate->snapshot ?? [];

        $relativePath = "certificates/{$certificate->serial_number}.pdf";
        $this->storage->replaceStoredFile($certificate->pdf_path, $relativePath);

        $pdf = Pdf::loadView('pdf.student-certificate', [
            'certificate' => $certificate,
            'student' => $student,
            'enrollment' => $enrollment,
            'institute' => InstituteSettings::forDocuments(),
            'title' => $certificate->type->documentTitle(),
            'body' => $this->certificateBody($certificate, $snapshot),
            'rollLabel' => StudentLabels::rollNumberLabel(),
            'batchLabel' => $snapshot['batch_label'] ?? null,
            'courseName' => $snapshot['course_name'] ?? ($enrollment?->course?->name),
            'sessionName' => $snapshot['session_name'] ?? null,
        ])
            ->setPaper('a4')
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', true)
            ->setOption('isHtml5ParserEnabled', true);

        Storage::disk(self::DISK)->put($relativePath, $pdf->output());

        $certificate->update(['pdf_path' => $relativePath]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function buildSnapshot(Student $student, Enrollment $enrollment, CertificateType $type): array
    {
        $batch = $student->activeBatchStudent?->batch;

        return [
            'student_name' => $student->name,
            'father_name' => $student->father_name,
            'date_of_birth' => $student->date_of_birth?->toDateString(),
            'gender' => $student->gender?->value,
            'mobile' => $student->mobile,
            'address' => $student->address,
            'enrollment_number' => $enrollment->enrollment_number,
            'course_name' => $enrollment->course?->name,
            'batch_label' => $batch ? ClassSectionLabel::forBatch($batch, includeSession: false) : null,
            'session_name' => $batch?->academicSession?->name,
            'enrolled_at' => $enrollment->enrolled_at?->toDateString(),
            'type' => $type->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $snapshot
     */
    protected function certificateBody(StudentCertificate $certificate, array $snapshot): string
    {
        $name = $snapshot['student_name'] ?? $certificate->student?->name ?? 'the student';
        $father = $snapshot['father_name'] ?? $certificate->student?->father_name;
        $course = $snapshot['course_name'] ?? 'their programme';
        $session = $snapshot['session_name'] ?? null;
        $batch = $snapshot['batch_label'] ?? null;
        $roll = $snapshot['enrollment_number'] ?? $certificate->enrollment?->enrollment_number;
        $dob = filled($snapshot['date_of_birth'] ?? null)
            ? \Carbon\Carbon::parse($snapshot['date_of_birth'])->format('d M Y')
            : null;

        $parentClause = filled($father) ? ", son/daughter of {$father}," : '';
        $sessionClause = filled($session) ? " for the academic session {$session}" : '';
        $batchClause = filled($batch) ? " ({$batch})" : '';
        $rollClause = filled($roll) ? " bearing {$this->rollLabel()} {$roll}" : '';

        return match ($certificate->type) {
            CertificateType::Bonafide => "This is to certify that {$name}{$parentClause} is a bonafide student of this institute, studying in {$course}{$batchClause}{$sessionClause}{$rollClause}.",
            CertificateType::Character => "This is to certify that {$name}{$parentClause} is/was a student of this institute studying in {$course}{$batchClause}{$sessionClause}. To the best of our knowledge, their conduct and character have been good.",
            CertificateType::Transfer => "This is to certify that {$name}{$parentClause} was a student of this institute in {$course}{$batchClause}{$sessionClause}{$rollClause}. This Transfer Certificate is issued on request for further studies elsewhere.",
            CertificateType::Birth => $dob
                ? "This is to certify that, according to our records, the date of birth of {$name}{$parentClause} is {$dob}."
                : "This is to certify that {$name}{$parentClause} is a student of this institute. Date of birth is not recorded in the system; please verify from supporting documents.",
            CertificateType::Fee => "This is to certify that {$name}{$parentClause}, studying in {$course}{$batchClause}{$sessionClause}{$rollClause}, has been a fee-paying student of this institute. This certificate is issued for fee-related verification purposes.",
        };
    }

    protected function rollLabel(): string
    {
        return StudentLabels::rollNumberLabel();
    }
}

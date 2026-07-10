<?php

namespace App\Services;

use App\Enums\DocumentType;
use App\Support\ClassSectionLabel;
use App\Support\InstituteSettings;
use App\Support\StudentLabels;
use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Models\User;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class IdCardService
{
    public const DISK = 'local';

    /** Standard landscape ID card in points (85.6mm × 54mm). */
    private const CARD_WIDTH_PT = 243;

    private const CARD_HEIGHT_PT = 153;

    public function __construct(
        protected AuditService $audit,
        protected StorageCleanupService $storage,
    ) {}

    public function shouldGenerateForFirstPayment(Payment $payment): bool
    {
        return Payment::query()
            ->where('fee_structure_id', $payment->fee_structure_id)
            ->count() === 1;
    }

    public function generateForEnrollment(Enrollment $enrollment, ?User $staff = null, bool $regenerate = false): Enrollment
    {
        if ($enrollment->hasIdCard() && ! $regenerate) {
            return $enrollment;
        }

        $enrollment->loadMissing([
            'student.activeBatchStudent.batch.academicSession',
            'course',
            'admission.documents',
        ]);

        $relativePath = "id_cards/{$enrollment->enrollment_number}.pdf";

        $this->storage->replaceStoredFile($enrollment->id_card_path, $relativePath);

        $verificationUrl = route('id-card.verify', ['enrollment' => $enrollment->enrollment_number]);
        $photo = $enrollment->admission?->documentForType(DocumentType::Photo)
            ?? $enrollment->student?->profilePhoto();

        $batch = $enrollment->student?->activeBatchStudent?->batch;
        $sessionName = $batch?->academicSession?->name;
        $batchLabel = $batch
            ? (filled($batch->section) ? 'Section '.$batch->section : $batch->name)
            : null;

        $validTill = $batch?->end_date?->format('Y')
            ?? $enrollment->enrolled_at?->copy()->addYears(2)->format('Y');

        $pdf = Pdf::loadView('pdf.student-id-card', [
            'student' => $enrollment->student,
            'enrollment' => $enrollment,
            'course' => $enrollment->course,
            'photoDataUri' => $this->photoDataUri($photo),
            'qrDataUri' => $this->qrDataUri($enrollment->enrollment_number, $verificationUrl),
            'institute' => InstituteSettings::forDocuments(),
            'rollLabel' => StudentLabels::rollNumberLabel(),
            'batchLabel' => $batchLabel,
            'sessionName' => $sessionName,
            'validTill' => $validTill,
            'batchFullLabel' => $batch ? ClassSectionLabel::forBatch($batch, includeSession: false) : null,
        ])
            ->setPaper([0, 0, self::CARD_WIDTH_PT, self::CARD_HEIGHT_PT])
            ->setOption('dpi', 96)
            ->setOption('defaultFont', 'DejaVu Sans')
            ->setOption('isRemoteEnabled', true);

        Storage::disk(self::DISK)->put($relativePath, $pdf->output());

        $enrollment->update([
            'id_card_path' => $relativePath,
            'id_card_generated_at' => now(),
        ]);

        $this->audit->log(
            action: $regenerate ? 'ID Card Regenerated' : 'ID Card Generated',
            auditable: $enrollment,
            newValues: [
                'enrollment_number' => $enrollment->enrollment_number,
                'id_card_path' => $relativePath,
            ],
            user: $staff,
        );

        return $enrollment->fresh([
            'student.activeBatchStudent.batch.academicSession',
            'course',
            'admission.documents',
        ]);
    }

    protected function photoDataUri(?Document $photo): ?string
    {
        if (! $photo?->isImage()) {
            return null;
        }

        if (! Storage::disk(DocumentService::DISK)->exists($photo->file_path)) {
            return null;
        }

        $contents = Storage::disk(DocumentService::DISK)->get($photo->file_path);
        $mime = Storage::disk(DocumentService::DISK)->mimeType($photo->file_path) ?: 'image/jpeg';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }

    protected function qrDataUri(string $enrollmentNumber, string $verificationUrl): string
    {
        $payload = $enrollmentNumber.'|'.$verificationUrl;

        $svg = QrCode::format('svg')
            ->size(120)
            ->margin(1)
            ->errorCorrection('M')
            ->generate($payload);

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}

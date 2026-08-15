<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Models\Enrollment;
use App\Models\Payment;
use App\Services\DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only integrity check: DB path exists but file is gone on disk.
 * Covers student documents plus receipts, ID cards, and payment proofs —
 * the folders nightly cleanup used to walk (documents are already excluded).
 */
class CrmCheckDocumentsCommand extends Command
{
    protected $signature = 'crm:check-documents
        {--limit=15 : How many missing files to list per section}
        {--scope=all : all|documents|payments|receipts|id_cards}';

    protected $description = 'Report CRM files missing on disk (documents, receipts, ID cards, payment proofs). Deletes nothing.';

    /**
     * Nightly cleanup stopped deleting documents/ on this date.
     * Receipts / ID cards / payment proofs stay in orphan prune until a later release.
     */
    private const DOCUMENT_CLEANUP_FIX_DATE = '2026-07-24';

    public function handle(): int
    {
        $disk = Storage::disk(DocumentService::DISK);
        $scope = strtolower((string) $this->option('scope'));
        $limit = max(1, (int) $this->option('limit'));

        $this->components->info('Storage root: '.$disk->path(''));
        $this->newLine();

        $anyMissing = false;

        if (in_array($scope, ['all', 'documents'], true)) {
            $anyMissing = $this->reportDocuments($disk, $limit) || $anyMissing;
        }

        if (in_array($scope, ['all', 'payments'], true)) {
            $anyMissing = $this->reportPaymentPaths($disk, $limit, 'proof_image_path', 'Payment proofs', 'payments') || $anyMissing;
        }

        if (in_array($scope, ['all', 'receipts'], true)) {
            $anyMissing = $this->reportPaymentPaths($disk, $limit, 'receipt_path', 'Receipt PDFs', 'receipts') || $anyMissing;
        }

        if (in_array($scope, ['all', 'id_cards'], true)) {
            $anyMissing = $this->reportIdCards($disk, $limit) || $anyMissing;
        }

        if (! $anyMissing) {
            $this->components->info('Every checked path that has a database value also has a file on disk.');
        }

        return self::SUCCESS;
    }

    private function reportDocuments($disk, int $limit): bool
    {
        $this->components->twoColumnDetail('Section', 'Student documents (photo / Aadhaar / …)');

        $total = 0;
        $missing = [];
        $missingByType = [];

        Document::query()
            ->orderBy('id')
            ->chunk(200, function ($documents) use ($disk, &$total, &$missing, &$missingByType): void {
                foreach ($documents as $document) {
                    $total++;

                    if (filled($document->file_path) && $disk->exists($document->file_path)) {
                        continue;
                    }

                    $missing[] = $document;
                    $type = $document->type?->value ?? 'unknown';
                    $missingByType[$type] = ($missingByType[$type] ?? 0) + 1;
                }
            });

        $missingCount = count($missing);
        $this->printCounts($total, $missingCount);

        foreach ($missingByType as $type => $count) {
            $this->components->twoColumnDetail("  missing · {$type}", (string) $count);
        }

        if ($missingCount === 0) {
            $this->components->info('Documents: all files present.');
            $this->newLine();

            return false;
        }

        $cutoff = Carbon::parse(self::DOCUMENT_CLEANUP_FIX_DATE)->startOfDay();
        $uploadedAfterFix = collect($missing)
            ->filter(fn (Document $document): bool => $document->created_at !== null
                && $document->created_at->greaterThanOrEqualTo($cutoff))
            ->count();
        $newest = collect($missing)
            ->filter(fn (Document $document): bool => $document->created_at !== null)
            ->max(fn (Document $document) => $document->created_at);

        $this->components->twoColumnDetail('Newest missing upload', $newest?->toDayDateTimeString() ?? 'unknown');
        $this->components->twoColumnDetail('Uploaded on/after '.self::DOCUMENT_CLEANUP_FIX_DATE, (string) $uploadedAfterFix);

        if ($uploadedAfterFix > 0) {
            $this->components->error('Documents uploaded after the cleanup fix are missing — something is still deleting them.');
        } else {
            $this->components->warn('Document gaps predate the cleanup fix — re-upload those files; they will stay.');
        }

        $this->table(
            ['Doc ID', 'Type', 'Owner', 'Uploaded', 'Missing path'],
            collect($missing)->take($limit)->map(fn (Document $document): array => [
                $document->id,
                $document->type?->value ?? '—',
                class_basename((string) $document->documentable_type).' #'.$document->documentable_id,
                $document->created_at?->toDateString() ?? '—',
                $document->file_path,
            ])->all(),
        );

        if ($missingCount > $limit) {
            $this->line('… and '.($missingCount - $limit).' more documents. Use --limit to see more.');
        }

        $this->newLine();

        return true;
    }

    private function reportPaymentPaths($disk, int $limit, string $column, string $label, string $folderHint): bool
    {
        $this->components->twoColumnDetail('Section', "{$label} ({$folderHint}/)");

        $total = 0;
        $missing = [];

        Payment::query()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->orderBy('id')
            ->chunk(200, function ($payments) use ($disk, $column, &$total, &$missing): void {
                foreach ($payments as $payment) {
                    $total++;
                    $path = (string) $payment->{$column};

                    if ($disk->exists($path)) {
                        continue;
                    }

                    $missing[] = [
                        'id' => $payment->id,
                        'student_id' => $payment->student_id,
                        'path' => $path,
                        'date' => $payment->created_at?->toDateString() ?? '—',
                    ];
                }
            });

        $missingCount = count($missing);
        $this->printCounts($total, $missingCount);

        if ($missingCount === 0) {
            $this->components->info("{$label}: all files present.");
            $this->newLine();

            return false;
        }

        $this->components->warn("{$label}: {$missingCount} path(s) point at missing files. Preview/download will 404 until re-generated or restored.");

        $this->table(
            ['Payment ID', 'Student', 'Uploaded', 'Missing path'],
            collect($missing)->take($limit)->map(fn (array $row): array => [
                $row['id'],
                '#'.$row['student_id'],
                $row['date'],
                $row['path'],
            ])->all(),
        );

        if ($missingCount > $limit) {
            $this->line('… and '.($missingCount - $limit).' more. Use --limit to see more.');
        }

        $this->newLine();

        return true;
    }

    private function reportIdCards($disk, int $limit): bool
    {
        $this->components->twoColumnDetail('Section', 'ID cards (id_cards/)');

        $total = 0;
        $missing = [];

        Enrollment::query()
            ->whereNotNull('id_card_path')
            ->where('id_card_path', '!=', '')
            ->orderBy('id')
            ->chunk(200, function ($enrollments) use ($disk, &$total, &$missing): void {
                foreach ($enrollments as $enrollment) {
                    $total++;
                    $path = (string) $enrollment->id_card_path;

                    if ($disk->exists($path)) {
                        continue;
                    }

                    $missing[] = [
                        'id' => $enrollment->id,
                        'student_id' => $enrollment->student_id,
                        'number' => $enrollment->enrollment_number ?? '—',
                        'path' => $path,
                        'date' => $enrollment->updated_at?->toDateString() ?? '—',
                    ];
                }
            });

        $missingCount = count($missing);
        $this->printCounts($total, $missingCount);

        if ($missingCount === 0) {
            $this->components->info('ID cards: all files present.');
            $this->newLine();

            return false;
        }

        $this->components->warn("ID cards: {$missingCount} path(s) point at missing files. Re-generate from the student profile.");

        $this->table(
            ['Enrollment', 'Student', 'Number', 'Updated', 'Missing path'],
            collect($missing)->take($limit)->map(fn (array $row): array => [
                $row['id'],
                '#'.$row['student_id'],
                $row['number'],
                $row['date'],
                $row['path'],
            ])->all(),
        );

        if ($missingCount > $limit) {
            $this->line('… and '.($missingCount - $limit).' more. Use --limit to see more.');
        }

        $this->newLine();

        return true;
    }

    private function printCounts(int $total, int $missingCount): void
    {
        $this->components->twoColumnDetail('Paths in database', (string) $total);
        $this->components->twoColumnDetail('Files present', (string) ($total - $missingCount));
        $this->components->twoColumnDetail('Files missing on disk', (string) $missingCount);
    }
}

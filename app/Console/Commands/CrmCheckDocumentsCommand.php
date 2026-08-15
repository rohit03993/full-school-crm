<?php

namespace App\Console\Commands;

use App\Models\Document;
use App\Services\DocumentService;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * Read-only check for student photos / documents that show as broken in the CRM:
 * the database row exists but the file is gone from storage, so preview returns 404.
 */
class CrmCheckDocumentsCommand extends Command
{
    protected $signature = 'crm:check-documents {--limit=15 : How many missing files to list}';

    protected $description = 'Report student documents whose file is missing on disk (broken photo previews). Deletes nothing.';

    /**
     * Nightly cleanup deleted files under documents/ before this release; anything
     * missing that was uploaded later points at a live problem instead of old damage.
     */
    private const CLEANUP_FIX_DATE = '2026-07-24';

    public function handle(): int
    {
        $disk = Storage::disk(DocumentService::DISK);

        $this->components->info('Storage root: '.$disk->path(''));

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

        $this->components->twoColumnDetail('Documents in database', (string) $total);
        $this->components->twoColumnDetail('Files present', (string) ($total - $missingCount));
        $this->components->twoColumnDetail('Files missing on disk', (string) $missingCount);

        if ($missingCount === 0) {
            $this->components->info('Every document file is present. Broken previews are not caused by missing files.');

            return self::SUCCESS;
        }

        foreach ($missingByType as $type => $count) {
            $this->components->twoColumnDetail("  missing · {$type}", (string) $count);
        }

        $cutoff = Carbon::parse(self::CLEANUP_FIX_DATE)->startOfDay();

        $uploadedAfterFix = collect($missing)
            ->filter(fn (Document $document): bool => $document->created_at !== null
                && $document->created_at->greaterThanOrEqualTo($cutoff))
            ->count();

        $newest = collect($missing)
            ->filter(fn (Document $document): bool => $document->created_at !== null)
            ->max(fn (Document $document) => $document->created_at);

        $this->newLine();
        $this->components->twoColumnDetail('Newest missing upload', $newest?->toDayDateTimeString() ?? 'unknown');
        $this->components->twoColumnDetail('Uploaded on/after '.self::CLEANUP_FIX_DATE, (string) $uploadedAfterFix);

        if ($uploadedAfterFix > 0) {
            $this->components->error('Files uploaded after the cleanup fix are missing — something is still deleting them. Send this output over.');
        } else {
            $this->components->warn('All missing files predate the cleanup fix, so this is old damage: re-upload those photos and they will stay.');
        }

        $limit = max(1, (int) $this->option('limit'));

        $this->newLine();
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
            $this->line('… and '.($missingCount - $limit).' more. Use --limit to see more.');
        }

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\MetaWhatsAppMessage;
use App\Services\MetaWhatsAppMediaService;
use App\Support\MetaWhatsAppInboundMessageParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

class SyncWhatsAppMediaCommand extends Command
{
    protected $signature = 'crm:sync-whatsapp-media
                            {--limit=100 : Max messages to process}
                            {--student= : Only sync media for a student id}';

    protected $description = 'Download and store missing WhatsApp media files from Meta (for inbox image/file display).';

    public function handle(MetaWhatsAppMediaService $media): int
    {
        if (! Schema::hasTable('meta_whatsapp_messages') || ! Schema::hasColumn('meta_whatsapp_messages', 'media_id')) {
            $this->error('meta_whatsapp_messages media columns are missing. Run php artisan migrate first.');

            return self::FAILURE;
        }

        $limit = max(1, (int) $this->option('limit'));
        $studentId = $this->option('student');

        $query = MetaWhatsAppMessage::query()
            ->where(function ($builder): void {
                $builder->whereNull('media_path')->orWhere('media_path', '');
            })
            ->orderByDesc('id');

        if (is_numeric($studentId)) {
            $query->where('student_id', (int) $studentId);
        }

        $candidates = $query->limit($limit * 5)->get()
            ->filter(fn (MetaWhatsAppMessage $message): bool => $media->needsMediaDownload($message))
            ->take($limit)
            ->values();

        if ($candidates->isEmpty()) {
            $this->info('No pending WhatsApp media to sync.');

            return self::SUCCESS;
        }

        $this->info('Syncing '.$candidates->count().' WhatsApp media file(s)…');

        $synced = $media->syncPendingDownloads($candidates, $candidates->count(), 30);

        $this->info("Stored {$synced} media file(s).");

        return self::SUCCESS;
    }
}

<?php

namespace App\Console\Commands;

use App\Models\MetaWhatsAppMessage;
use App\Models\Student;
use App\Services\MetaWhatsAppMediaService;
use App\Services\MetaWhatsAppService;
use App\Support\MetaWhatsAppInboundMessageParser;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class InspectWhatsAppMediaCommand extends Command
{
    protected $signature = 'crm:inspect-whatsapp-media
                            {--student= : Student id (e.g. 7 for Amit Verma)}
                            {--limit=15 : Rows to show}';

    protected $description = 'Diagnose why WhatsApp inbox photos/files are not showing.';

    public function handle(MetaWhatsAppMediaService $media, MetaWhatsAppService $meta): int
    {
        $this->components->info('WhatsApp media diagnostics');

        $columns = ['message_type', 'media_id', 'media_path', 'caption'];

        foreach ($columns as $column) {
            if (! Schema::hasColumn('meta_whatsapp_messages', $column)) {
                $this->components->error("Column meta_whatsapp_messages.{$column} is missing — run: php artisan migrate --force");

                return self::FAILURE;
            }
        }

        $mediaDir = storage_path('app/private/'.MetaWhatsAppMediaService::DIRECTORY);
        $this->line('Storage dir: '.$mediaDir.' · writable: '.(is_dir($mediaDir) && is_writable($mediaDir) ? 'yes' : 'no'));
        $this->line('Meta configured: '.($meta->isConfigured() ? 'yes' : 'no'));

        $studentId = $this->option('student');
        $limit = max(1, (int) $this->option('limit'));

        $query = MetaWhatsAppMessage::query()
            ->where('direction', 'inbound')
            ->orderByDesc('id');

        if (is_numeric($studentId)) {
            $student = Student::query()->find((int) $studentId);
            $this->line('Student: '.($student?->name ?? 'not found').' (id '.(int) $studentId.')');
            $query->where('student_id', (int) $studentId);
        }

        $rows = $query->limit($limit)->get();

        if ($rows->isEmpty()) {
            $this->warn('No inbound messages found.');

            return self::SUCCESS;
        }

        $table = [];

        foreach ($rows as $row) {
            $payloadType = is_array($row->payload) ? (string) ($row->payload['type'] ?? '') : '';
            $needs = $media->needsMediaDownload($row);
            $fileOk = filled($row->media_path)
                && Storage::disk(MetaWhatsAppMediaService::DISK)->exists((string) $row->media_path);

            $table[] = [
                $row->id,
                $row->created_at?->format('d M H:i'),
                (string) ($row->message_type ?? 'text'),
                $payloadType !== '' ? $payloadType : '—',
                filled($row->media_id) ? 'yes' : 'no',
                $fileOk ? 'yes' : 'no',
                $needs ? 'download' : ($fileOk ? 'ok' : 'n/a'),
                str((string) ($row->body_preview ?? ''))->limit(28),
            ];
        }

        $this->table(
            ['id', 'at', 'db_type', 'payload_type', 'media_id', 'file', 'status', 'preview'],
            $table,
        );

        $mediaRows = $rows->filter(
            fn (MetaWhatsAppMessage $row): bool => MetaWhatsAppInboundMessageParser::isMediaType((string) ($row->message_type ?? 'text'))
                || (is_array($row->payload) && MetaWhatsAppInboundMessageParser::isMediaType((string) ($row->payload['type'] ?? ''))),
        );

        if ($mediaRows->isEmpty()) {
            $this->warn('None of these rows look like image/file messages. Parent may have sent text only, or payload was not stored.');
        } else {
            $missing = $mediaRows->filter(fn (MetaWhatsAppMessage $row): bool => $media->needsMediaDownload($row))->count();
            if ($missing > 0) {
                $this->line("{$missing} media message(s) still need download. Run: php artisan crm:sync-whatsapp-media".(is_numeric($studentId) ? ' --student='.(int) $studentId : ''));
            }
        }

        return self::SUCCESS;
    }
}

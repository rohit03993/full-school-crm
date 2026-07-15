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
        $privateDir = storage_path('app/private');
        $mediaWritable = is_dir($mediaDir) && is_writable($mediaDir);
        $privateWritable = is_dir($privateDir) && is_writable($privateDir);

        $this->line('Storage dir: '.$mediaDir.' · writable: '.($mediaWritable ? 'yes' : 'no'));
        $this->line('Private storage: '.$privateDir.' · writable: '.($privateWritable ? 'yes' : 'no'));
        $this->line('Meta configured: '.($meta->isConfigured() ? 'yes' : 'no'));
        $this->line('Webhook URL: '.$meta->webhookUrl());
        $this->line('Inbound trace log: '.storage_path('logs/whatsapp-inbound.log'));

        if ($meta->isConfigured()) {
            $connection = $meta->validateConnection();
            $displayNumber = (string) ($connection['display_phone_number'] ?? '');

            if ($displayNumber !== '') {
                $this->line('Meta business number: '.$displayNumber.' (parents must message this number)');
            } elseif (($connection['status'] ?? '') === 'failed') {
                $this->line('Meta connection check failed: '.($connection['message'] ?? 'unknown error'));
            }
        }

        if (! $mediaWritable) {
            $this->newLine();
            $this->components->warn('WhatsApp media cannot be saved until storage is writable. On the server (as root):');
            $this->line('  mkdir -p '.$mediaDir);
            $this->line('  chown -R www-data:www-data '.$privateDir);
            $this->line('  chmod -R 775 '.$privateDir);
            $this->newLine();
        }

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
            $this->newLine();
            $this->components->warn('Meta delivered these as plain TEXT messages (payload type = text), not image/file webhooks.');
            $this->line('The CRM cannot show photos that were never received with a media_id.');
            $this->line('Ask the parent to send a fresh photo to your WhatsApp Business number after storage is writable.');
            $this->line('Then run: tail -5 storage/logs/whatsapp-inbound.log');
            $this->line('You should see "type":"image" and a media_id when a real photo arrives.');

            $sample = $rows->first();
            if ($sample && is_array($sample->payload)) {
                $this->newLine();
                $this->line('Latest payload sample (id '.$sample->id.'):');
                $this->line(json_encode($sample->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            }
        } else {
            $missing = $mediaRows->filter(fn (MetaWhatsAppMessage $row): bool => $media->needsMediaDownload($row))->count();
            if ($missing > 0) {
                $this->line("{$missing} media message(s) still need download. Run: php artisan crm:sync-whatsapp-media".(is_numeric($studentId) ? ' --student='.(int) $studentId : ''));
            }
        }

        return self::SUCCESS;
    }
}

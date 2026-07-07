<?php

namespace App\Support;

use Illuminate\Support\Facades\File;

class MetaWhatsAppWebhookTrace
{
    public const LOG_FILE = 'logs/whatsapp-inbound.log';

    /**
     * @param  array<string, mixed>  $context
     */
    public static function write(string $event, array $context = []): void
    {
        $line = json_encode([
            'at' => now()->toIso8601String(),
            'event' => $event,
            ...$context,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        if ($line === false) {
            return;
        }

        try {
            $path = storage_path(self::LOG_FILE);
            File::ensureDirectoryExists(dirname($path));
            File::append($path, $line.PHP_EOL);
        } catch (\Throwable) {
            // Never break webhook handling because trace logging failed.
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public static function summarizeInboundTypes(array $payload): array
    {
        $summary = [];

        foreach ($payload['entry'] ?? [] as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            foreach ($entry['changes'] ?? [] as $change) {
                if (! is_array($change)) {
                    continue;
                }

                $field = (string) ($change['field'] ?? 'unknown');
                $value = $change['value'] ?? null;

                if (! is_array($value)) {
                    continue;
                }

                foreach ($value['messages'] ?? [] as $message) {
                    if (! is_array($message)) {
                        continue;
                    }

                    $type = (string) ($message['type'] ?? 'unknown');
                    $mediaId = (string) data_get($message, "{$type}.id", data_get($message, 'image.id', ''));

                    $summary[] = [
                        'field' => $field,
                        'from' => (string) ($message['from'] ?? ''),
                        'wamid' => (string) ($message['id'] ?? ''),
                        'type' => $type,
                        'media_id' => $mediaId !== '' ? $mediaId : null,
                    ];
                }

                foreach ($value['statuses'] ?? [] as $status) {
                    if (! is_array($status)) {
                        continue;
                    }

                    $summary[] = [
                        'field' => $field,
                        'status_for' => (string) ($status['id'] ?? ''),
                        'type' => 'status:'.(string) ($status['status'] ?? 'unknown'),
                    ];
                }
            }
        }

        return $summary;
    }
}

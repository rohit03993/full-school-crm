<?php

namespace App\Services;

use App\Models\MetaWhatsAppMessage;
use App\Support\MetaWhatsAppInboundMessageParser;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class MetaWhatsAppMediaService
{
    public const DISK = 'local';

    public const DIRECTORY = 'whatsapp-media';

    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    public function mediaUrl(MetaWhatsAppMessage $message): ?string
    {
        if (! filled($message->media_path)) {
            return null;
        }

        if (! Storage::disk(self::DISK)->exists((string) $message->media_path)) {
            return null;
        }

        try {
            return route('admin.whatsapp-messages.media', ['message' => $message->id]);
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp media route unavailable', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    public function prepareForThreadDisplay(MetaWhatsAppMessage $message): MetaWhatsAppMessage
    {
        if (! Schema::hasColumn('meta_whatsapp_messages', 'message_type')) {
            return $message;
        }

        return $this->hydrateMediaFieldsFromPayload($message);
    }

    public function ensureStored(MetaWhatsAppMessage $message): MetaWhatsAppMessage
    {
        $message = $this->hydrateMediaFieldsFromPayload($message);

        if (! MetaWhatsAppInboundMessageParser::isMediaType((string) ($message->message_type ?? 'text'))) {
            return $message;
        }

        if (filled($message->media_path) && Storage::disk(self::DISK)->exists((string) $message->media_path)) {
            return $message;
        }

        if (blank($message->media_id)) {
            $message->media_id = $this->mediaIdFromPayload($message);
        }

        if (blank($message->media_id)) {
            return $message;
        }

        return $this->downloadInboundMedia($message) ?? $message;
    }

    public function hydrateMediaFieldsFromPayload(MetaWhatsAppMessage $message): MetaWhatsAppMessage
    {
        if (! Schema::hasColumn('meta_whatsapp_messages', 'message_type')) {
            return $message;
        }

        if (MetaWhatsAppInboundMessageParser::isMediaType((string) ($message->message_type ?? 'text'))
            && filled($message->media_id)
            && ! MetaWhatsAppInboundMessageParser::isPlaceholderPreview((string) ($message->body_preview ?? ''))) {
            return $message;
        }

        $payload = is_array($message->payload) ? $message->payload : [];

        if ($payload === [] || blank($payload['type'] ?? null)) {
            return $message;
        }

        $parsed = MetaWhatsAppInboundMessageParser::parse($payload);

        if (! MetaWhatsAppInboundMessageParser::isMediaType($parsed['message_type'])) {
            return $message;
        }

        try {
            $message->update([
                'message_type' => $parsed['message_type'],
                'media_id' => $parsed['media_id'] ?? $message->media_id,
                'media_mime_type' => $parsed['media_mime_type'] ?? $message->media_mime_type,
                'media_filename' => $parsed['media_filename'] ?? $message->media_filename,
                'caption' => $parsed['caption'] ?? $message->caption,
                'body_preview' => MetaWhatsAppInboundMessageParser::isPlaceholderPreview((string) ($message->body_preview ?? ''))
                    ? $parsed['body_preview']
                    : $message->body_preview,
            ]);
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp media metadata hydrate failed', [
                'message_id' => $message->id,
                'error' => $exception->getMessage(),
            ]);

            return $message;
        }

        return $message->fresh() ?? $message;
    }

    /**
     * Download missing inbound media for messages already in the thread (AiSensy-style lazy fetch).
     *
     * @param  iterable<MetaWhatsAppMessage>  $messages
     */
    public function syncPendingDownloads(iterable $messages, int $limit = 5, int $timeoutSeconds = 12): int
    {
        if (! $this->meta->isConfigured()) {
            return 0;
        }

        $synced = 0;

        foreach ($messages as $message) {
            if ($synced >= $limit) {
                break;
            }

            if (! $message instanceof MetaWhatsAppMessage) {
                continue;
            }

            $message = $this->hydrateMediaFieldsFromPayload($message);

            if (! MetaWhatsAppInboundMessageParser::isMediaType((string) ($message->message_type ?? 'text'))) {
                continue;
            }

            if (filled($message->media_path) && Storage::disk(self::DISK)->exists((string) $message->media_path)) {
                continue;
            }

            $mediaId = (string) ($message->media_id ?: $this->mediaIdFromPayload($message) ?: '');

            if ($mediaId === '') {
                continue;
            }

            if (blank($message->media_id)) {
                $message->update(['media_id' => $mediaId]);
                $message = $message->fresh() ?? $message;
            }

            if ($this->downloadInboundMedia($message, $timeoutSeconds) !== null) {
                $synced++;
            }
        }

        return $synced;
    }

    public function downloadInboundMedia(MetaWhatsAppMessage $message, int $timeoutSeconds = 30): ?MetaWhatsAppMessage
    {
        $mediaId = (string) ($message->media_id ?? '');

        if ($mediaId === '' || ! $this->meta->isConfigured()) {
            return null;
        }

        if (filled($message->media_path) && Storage::disk(self::DISK)->exists((string) $message->media_path)) {
            return $message;
        }

        try {
            $metaResponse = Http::timeout($timeoutSeconds)
                ->withToken((string) $this->meta->accessToken())
                ->acceptJson()
                ->get($this->meta->graphUrl($mediaId));

            if (! $metaResponse->successful()) {
                Log::warning('Meta WhatsApp media metadata fetch failed', [
                    'message_id' => $message->id,
                    'media_id' => $mediaId,
                    'status' => $metaResponse->status(),
                ]);

                return null;
            }

            $downloadUrl = (string) data_get($metaResponse->json(), 'url', '');
            $mimeType = (string) data_get($metaResponse->json(), 'mime_type', (string) ($message->media_mime_type ?? ''));

            if ($downloadUrl === '') {
                return null;
            }

            $binaryResponse = Http::timeout(max($timeoutSeconds, 20))
                ->withToken((string) $this->meta->accessToken())
                ->get($downloadUrl);

            if (! $binaryResponse->successful()) {
                Log::warning('Meta WhatsApp media download failed', [
                    'message_id' => $message->id,
                    'media_id' => $mediaId,
                    'status' => $binaryResponse->status(),
                ]);

                return null;
            }

            $extension = $this->extensionForMime($mimeType, (string) ($message->message_type ?? 'file'));
            $filename = $this->buildStoredFilename($message, $extension);
            $path = self::DIRECTORY.'/'.$filename;

            Storage::disk(self::DISK)->put($path, $binaryResponse->body());

            $message->update([
                'media_id' => $mediaId,
                'media_path' => $path,
                'media_mime_type' => $mimeType !== '' ? $mimeType : $message->media_mime_type,
                'media_filename' => $message->media_filename ?: basename($path),
            ]);

            return $message->fresh();
        } catch (\Throwable $exception) {
            Log::warning('Meta WhatsApp media download exception', [
                'message_id' => $message->id,
                'media_id' => $mediaId,
                'error' => $exception->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array{status: string, media_id?: string, error?: string}
     */
    public function uploadOutboundFile(UploadedFile $file): array
    {
        if (! $this->meta->isConfigured()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not configured.'];
        }

        $mimeType = (string) ($file->getMimeType() ?: 'application/octet-stream');
        $messageType = $this->messageTypeForMime($mimeType);

        if ($messageType === null) {
            return ['status' => 'failed', 'error' => 'Unsupported file type. Send an image, video, PDF, or document.'];
        }

        if (! $this->isWithinSizeLimit($file, $messageType)) {
            return ['status' => 'failed', 'error' => 'File is too large for WhatsApp.'];
        }

        try {
            $response = Http::timeout(60)
                ->withToken((string) $this->meta->accessToken())
                ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post($this->meta->graphUrl($this->meta->phoneNumberId().'/media'), [
                    'messaging_product' => 'whatsapp',
                    'type' => $mimeType,
                ]);

            $data = $response->json();
            $mediaId = data_get($data, 'id');

            if ($response->successful() && is_string($mediaId) && $mediaId !== '') {
                return ['status' => 'success', 'media_id' => $mediaId, 'message_type' => $messageType, 'mime_type' => $mimeType];
            }

            return [
                'status' => 'failed',
                'error' => $this->meta->parseApiError($data, $response->body()),
            ];
        } catch (\Throwable $exception) {
            Log::error('Meta WhatsApp media upload exception', ['error' => $exception->getMessage()]);

            return ['status' => 'failed', 'error' => $exception->getMessage()];
        }
    }

    public function storeOutboundCopy(MetaWhatsAppMessage $message, UploadedFile $file): void
    {
        $extension = $file->getClientOriginalExtension() ?: $this->extensionForMime((string) $file->getMimeType(), (string) $message->message_type);
        $path = self::DIRECTORY.'/out-'.$message->id.'-'.Str::random(8).'.'.$extension;

        Storage::disk(self::DISK)->put($path, file_get_contents($file->getRealPath()));

        $message->update([
            'media_path' => $path,
            'media_mime_type' => (string) ($file->getMimeType() ?: $message->media_mime_type),
            'media_filename' => $file->getClientOriginalName(),
        ]);
    }

    protected function mediaIdFromPayload(MetaWhatsAppMessage $message): ?string
    {
        $payload = is_array($message->payload) ? $message->payload : [];
        $type = (string) ($message->message_type ?? '');

        if ($type === '' || $type === 'text') {
            $type = (string) ($payload['type'] ?? '');
        }

        if ($type === '') {
            return null;
        }

        $mediaId = (string) data_get($payload, "{$type}.id", '');

        return $mediaId !== '' ? $mediaId : null;
    }

    protected function buildStoredFilename(MetaWhatsAppMessage $message, string $extension): string
    {
        $base = 'in-'.$message->id;

        if (filled($message->media_filename)) {
            $slug = Str::slug(pathinfo((string) $message->media_filename, PATHINFO_FILENAME));

            if ($slug !== '') {
                $base .= '-'.$slug;
            }
        }

        return $base.'-'.Str::random(6).'.'.$extension;
    }

    protected function extensionForMime(string $mimeType, string $messageType): string
    {
        return match (true) {
            str_contains($mimeType, 'jpeg') => 'jpg',
            str_contains($mimeType, 'png') => 'png',
            str_contains($mimeType, 'webp') => 'webp',
            str_contains($mimeType, 'gif') => 'gif',
            str_contains($mimeType, 'mp4') => 'mp4',
            str_contains($mimeType, 'mpeg') => 'mp3',
            str_contains($mimeType, 'ogg') => 'ogg',
            str_contains($mimeType, 'pdf') => 'pdf',
            $messageType === 'sticker' => 'webp',
            $messageType === 'video' => 'mp4',
            $messageType === 'audio' => 'ogg',
            $messageType === 'image' => 'jpg',
            default => 'bin',
        };
    }

    protected function messageTypeForMime(string $mimeType): ?string
    {
        if (str_starts_with($mimeType, 'image/')) {
            return 'image';
        }

        if (str_starts_with($mimeType, 'video/')) {
            return 'video';
        }

        if (str_starts_with($mimeType, 'audio/')) {
            return 'audio';
        }

        return 'document';
    }

    protected function isWithinSizeLimit(UploadedFile $file, string $messageType): bool
    {
        $bytes = (int) $file->getSize();

        return match ($messageType) {
            'image' => $bytes <= 5 * 1024 * 1024,
            'video' => $bytes <= 16 * 1024 * 1024,
            'audio' => $bytes <= 16 * 1024 * 1024,
            default => $bytes <= 100 * 1024 * 1024,
        };
    }
}

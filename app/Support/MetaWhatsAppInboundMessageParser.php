<?php

namespace App\Support;

class MetaWhatsAppInboundMessageParser
{
    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message_type: string,
     *     body_preview: string,
     *     media_id: ?string,
     *     media_mime_type: ?string,
     *     media_filename: ?string,
     *     caption: ?string
     * }
     */
    public static function parse(array $message): array
    {
        $type = (string) ($message['type'] ?? 'text');

        return match ($type) {
            'text' => [
                'message_type' => 'text',
                'body_preview' => (string) data_get($message, 'text.body', ''),
                'media_id' => null,
                'media_mime_type' => (string) data_get($message, 'text.mime_type', '') ?: null,
                'media_filename' => null,
                'caption' => null,
            ],
            'image' => self::mediaMessage('image', $message, '📷 Photo'),
            'video' => self::mediaMessage('video', $message, '🎬 Video'),
            'audio' => self::mediaMessage('audio', $message, '🎤 Voice message'),
            'document' => self::documentMessage($message),
            'sticker' => self::mediaMessage('sticker', $message, 'Sticker'),
            'location' => self::locationMessage($message),
            'reaction' => self::reactionMessage($message),
            'button' => [
                'message_type' => 'button',
                'body_preview' => (string) data_get($message, 'button.text', '[button]'),
                'media_id' => null,
                'media_mime_type' => null,
                'media_filename' => null,
                'caption' => null,
            ],
            'interactive' => [
                'message_type' => 'interactive',
                'body_preview' => (string) data_get($message, 'interactive.button_reply.title', data_get($message, 'interactive.list_reply.title', '[interactive message]')),
                'media_id' => null,
                'media_mime_type' => null,
                'media_filename' => null,
                'caption' => null,
            ],
            default => [
                'message_type' => $type !== '' ? $type : 'unknown',
                'body_preview' => self::typeLabel($type),
                'media_id' => self::mediaIdFromPayload($message, $type),
                'media_mime_type' => (string) data_get($message, "{$type}.mime_type", '') ?: null,
                'media_filename' => (string) data_get($message, "{$type}.filename", '') ?: null,
                'caption' => self::captionFromPayload($message, $type),
            ],
        };
    }

    public static function previewLabel(?string $messageType, ?string $bodyPreview, ?string $caption = null): string
    {
        $caption = trim((string) $caption);
        $bodyPreview = trim((string) $bodyPreview);

        if ($caption !== '') {
            return $caption;
        }

        if ($bodyPreview !== '' && ! self::isPlaceholderPreview($bodyPreview)) {
            return $bodyPreview;
        }

        return self::typeLabel((string) $messageType);
    }

    public static function isPlaceholderPreview(string $preview): bool
    {
        return (bool) preg_match('/^\[[a-z_]+ message\]$/i', trim($preview));
    }

    public static function isMediaType(?string $messageType): bool
    {
        return in_array((string) $messageType, ['image', 'video', 'audio', 'document', 'sticker'], true);
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message_type: string,
     *     body_preview: string,
     *     media_id: ?string,
     *     media_mime_type: ?string,
     *     media_filename: ?string,
     *     caption: ?string
     * }
     */
    protected static function mediaMessage(string $type, array $message, string $fallbackLabel): array
    {
        $caption = self::captionFromPayload($message, $type);

        return [
            'message_type' => $type,
            'body_preview' => $caption !== '' ? $caption : $fallbackLabel,
            'media_id' => self::mediaIdFromPayload($message, $type),
            'media_mime_type' => (string) data_get($message, "{$type}.mime_type", '') ?: null,
            'media_filename' => (string) data_get($message, "{$type}.filename", '') ?: null,
            'caption' => $caption !== '' ? $caption : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message_type: string,
     *     body_preview: string,
     *     media_id: ?string,
     *     media_mime_type: ?string,
     *     media_filename: ?string,
     *     caption: ?string
     * }
     */
    protected static function documentMessage(array $message): array
    {
        $filename = trim((string) data_get($message, 'document.filename', ''));
        $caption = self::captionFromPayload($message, 'document');

        return [
            'message_type' => 'document',
            'body_preview' => $caption !== '' ? $caption : ($filename !== '' ? '📄 '.$filename : '📄 Document'),
            'media_id' => self::mediaIdFromPayload($message, 'document'),
            'media_mime_type' => (string) data_get($message, 'document.mime_type', '') ?: null,
            'media_filename' => $filename !== '' ? $filename : null,
            'caption' => $caption !== '' ? $caption : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message_type: string,
     *     body_preview: string,
     *     media_id: ?string,
     *     media_mime_type: ?string,
     *     media_filename: ?string,
     *     caption: ?string
     * }
     */
    protected static function locationMessage(array $message): array
    {
        $name = trim((string) data_get($message, 'location.name', ''));
        $address = trim((string) data_get($message, 'location.address', ''));
        $label = $name !== '' ? $name : ($address !== '' ? $address : '📍 Location');

        return [
            'message_type' => 'location',
            'body_preview' => $label,
            'media_id' => null,
            'media_mime_type' => null,
            'media_filename' => null,
            'caption' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     * @return array{
     *     message_type: string,
     *     body_preview: string,
     *     media_id: ?string,
     *     media_mime_type: ?string,
     *     media_filename: ?string,
     *     caption: ?string
     * }
     */
    protected static function reactionMessage(array $message): array
    {
        $emoji = trim((string) data_get($message, 'reaction.emoji', ''));

        return [
            'message_type' => 'reaction',
            'body_preview' => $emoji !== '' ? $emoji.' reaction' : 'Reaction',
            'media_id' => null,
            'media_mime_type' => null,
            'media_filename' => null,
            'caption' => null,
        ];
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected static function mediaIdFromPayload(array $message, string $type): ?string
    {
        $mediaId = (string) data_get($message, "{$type}.id", '');

        return $mediaId !== '' ? $mediaId : null;
    }

    /**
     * @param  array<string, mixed>  $message
     */
    protected static function captionFromPayload(array $message, string $type): ?string
    {
        $caption = trim((string) data_get($message, "{$type}.caption", ''));

        return $caption !== '' ? $caption : null;
    }

    protected static function typeLabel(string $type): string
    {
        return match ($type) {
            'text' => 'Message',
            'image' => '📷 Photo',
            'video' => '🎬 Video',
            'audio' => '🎤 Voice message',
            'document' => '📄 Document',
            'sticker' => 'Sticker',
            'location' => '📍 Location',
            'reaction' => 'Reaction',
            'button' => 'Button reply',
            'interactive' => 'Interactive reply',
            default => $type !== '' ? ucfirst(str_replace('_', ' ', $type)) : 'Message',
        };
    }
}

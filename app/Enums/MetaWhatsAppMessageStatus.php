<?php

namespace App\Enums;

enum MetaWhatsAppMessageStatus: string
{
    case Queued = 'queued';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Read = 'read';
    case Failed = 'failed';
    case Received = 'received';

    public function label(): string
    {
        return match ($this) {
            self::Queued => 'Queued',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Read => 'Read',
            self::Failed => 'Failed',
            self::Received => 'Received',
        };
    }

    public static function fromWebhookStatus(string $status): ?self
    {
        return match (strtolower($status)) {
            'sent' => self::Sent,
            'delivered' => self::Delivered,
            'read' => self::Read,
            'failed' => self::Failed,
            default => null,
        };
    }
}

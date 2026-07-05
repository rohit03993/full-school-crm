<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Models\MetaWhatsAppMessage;

class MetaWhatsAppDashboardService
{
    /**
     * @return array{total: int, outbound: int, inbound: int, delivered: int}
     */
    public function stats(): array
    {
        return [
            'total' => MetaWhatsAppMessage::query()->count(),
            'outbound' => MetaWhatsAppMessage::query()
                ->where('direction', MetaWhatsAppMessageDirection::Outbound->value)
                ->count(),
            'inbound' => MetaWhatsAppMessage::query()
                ->where('direction', MetaWhatsAppMessageDirection::Inbound->value)
                ->count(),
            'delivered' => MetaWhatsAppMessage::query()
                ->whereIn('status', [
                    MetaWhatsAppMessageStatus::Delivered->value,
                    MetaWhatsAppMessageStatus::Read->value,
                ])
                ->count(),
        ];
    }
}

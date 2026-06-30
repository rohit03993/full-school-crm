<?php

namespace App\Support;

class PunchWhatsappStatus
{
    /**
     * @return array{label: string, tone: string}
     */
    public static function chip(?string $status): array
    {
        $normalized = strtolower(trim((string) $status));

        return match (true) {
            in_array($normalized, ['success', 'sent', 'delivered'], true) => [
                'label' => 'Sent',
                'tone' => 'success',
            ],
            in_array($normalized, ['failed', 'error'], true) => [
                'label' => 'Failed',
                'tone' => 'danger',
            ],
            in_array($normalized, ['queued', 'pending', 'processing'], true) => [
                'label' => 'Queued',
                'tone' => 'warning',
            ],
            default => [
                'label' => 'Not sent',
                'tone' => 'muted',
            ],
        };
    }
}

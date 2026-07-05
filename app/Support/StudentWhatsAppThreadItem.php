<?php

namespace App\Support;

use Carbon\Carbon;

readonly class StudentWhatsAppThreadItem
{
    public function __construct(
        public string $key,
        public string $source,
        public string $direction,
        public string $body,
        public string $status,
        public string $statusLabel,
        public ?Carbon $at,
        public ?string $templateName = null,
        public ?string $provider = null,
    ) {}

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }
}

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
        public ?string $errorMessage = null,
    ) {}

    public function isInbound(): bool
    {
        return $this->direction === 'inbound';
    }

    public function isOutbound(): bool
    {
        return $this->direction === 'outbound';
    }

    /**
     * @return array{key: string, source: string, direction: string, body: string, status: string, statusLabel: string, at: ?string, at_label: ?string, templateName: ?string, provider: ?string, errorMessage: ?string}
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key,
            'source' => $this->source,
            'direction' => $this->direction,
            'body' => $this->body,
            'status' => $this->status,
            'statusLabel' => $this->statusLabel,
            'at' => $this->at?->toIso8601String(),
            'at_label' => $this->at?->format('d M, h:i A'),
            'templateName' => $this->templateName,
            'provider' => $this->provider,
            'errorMessage' => $this->errorMessage,
        ];
    }
}

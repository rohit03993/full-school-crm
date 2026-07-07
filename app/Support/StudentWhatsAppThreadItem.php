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
        public string $messageType = 'text',
        public ?string $mediaUrl = null,
        public ?string $mediaMimeType = null,
        public ?string $mediaFilename = null,
        public ?string $caption = null,
        public ?string $locationUrl = null,
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
     * @return array<string, mixed>
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
            'messageType' => $this->messageType,
            'mediaUrl' => $this->mediaUrl,
            'mediaMimeType' => $this->mediaMimeType,
            'mediaFilename' => $this->mediaFilename,
            'caption' => $this->caption,
            'locationUrl' => $this->locationUrl,
        ];
    }
}

<?php

namespace App\Jobs;

use App\Models\MetaWhatsAppMessage;
use App\Services\MetaWhatsAppMediaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DownloadMetaWhatsAppMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [30, 120, 300];

    public function __construct(public int $messageId) {}

    public function handle(MetaWhatsAppMediaService $media): void
    {
        $message = MetaWhatsAppMessage::query()->find($this->messageId);

        if (! $message) {
            return;
        }

        $media->downloadInboundMedia($message);
    }
}

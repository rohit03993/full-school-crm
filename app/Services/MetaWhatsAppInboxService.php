<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\WhatsAppMessageSource;
use App\Models\Student;
use App\Models\User;
use App\Support\MetaWhatsAppInboundMessageParser;
use Illuminate\Http\UploadedFile;

class MetaWhatsAppInboxService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppMessageLogger $logger,
        protected StudentWhatsAppThreadService $thread,
        protected WhatsAppProviderResolver $resolver,
        protected MetaWhatsAppMediaService $media,
    ) {}

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function sendReply(
        Student $student,
        string $text,
        ?User $sender = null,
        WhatsAppMessageSource $source = WhatsAppMessageSource::Profile,
    ): array {
        if (! $this->resolver->isMetaActive()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not enabled. Open Connection & Setup and turn on WhatsApp.'];
        }

        if (blank($student->mobile)) {
            return ['status' => 'failed', 'error' => 'Student has no mobile number.'];
        }

        $text = trim($text);

        if ($text === '') {
            return ['status' => 'failed', 'error' => 'Message text is required.'];
        }

        if (! $this->thread->sessionOpenForStudent($student)) {
            return [
                'status' => 'failed',
                'error' => 'The 24-hour reply window is closed. Ask the parent to message you first, or send an approved template.',
            ];
        }

        $result = $this->meta->sendText((string) $student->mobile, $text);

        if ($result['status'] === 'success') {
            $this->logger->recordOutboundText(
                (string) $student->mobile,
                $result['message_id'] ?? null,
                $text,
                MetaWhatsAppMessageStatus::Sent,
                $sender?->name ? 'Reply by '.$sender->name : 'Session reply',
                is_array($result['response'] ?? null) ? $result['response'] : null,
                $student->id,
                ['message_source' => $source->value],
            );
        }

        return $result;
    }

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function sendMedia(
        Student $student,
        UploadedFile $file,
        ?string $caption = null,
        ?User $sender = null,
        WhatsAppMessageSource $source = WhatsAppMessageSource::Profile,
    ): array {
        if (! $this->resolver->isMetaActive()) {
            return ['status' => 'failed', 'error' => 'WhatsApp is not enabled. Open Connection & Setup and turn on WhatsApp.'];
        }

        if (blank($student->mobile)) {
            return ['status' => 'failed', 'error' => 'Student has no mobile number.'];
        }

        if (! $this->thread->sessionOpenForStudent($student)) {
            return [
                'status' => 'failed',
                'error' => 'The 24-hour reply window is closed. Ask the parent to message you first, or send an approved template.',
            ];
        }

        $upload = $this->media->uploadOutboundFile($file);

        if ($upload['status'] !== 'success' || blank($upload['media_id'] ?? null)) {
            return ['status' => 'failed', 'error' => (string) ($upload['error'] ?? 'Could not upload file to WhatsApp.')];
        }

        $messageType = (string) ($upload['message_type'] ?? 'document');
        $caption = trim((string) $caption);
        $filename = $file->getClientOriginalName();

        $result = $this->meta->sendMedia(
            (string) $student->mobile,
            $messageType,
            (string) $upload['media_id'],
            $caption !== '' ? $caption : null,
            $messageType === 'document' ? $filename : null,
        );

        if ($result['status'] !== 'success') {
            return $result;
        }

        $preview = MetaWhatsAppInboundMessageParser::previewLabel(
            $messageType,
            null,
            $caption !== '' ? $caption : null,
        );

        $logged = $this->logger->recordOutboundMedia(
            (string) $student->mobile,
            $result['message_id'] ?? null,
            [
                'body_preview' => $preview,
                'message_type' => $messageType,
                'media_id' => $upload['media_id'],
                'media_mime_type' => $upload['mime_type'] ?? $file->getMimeType(),
                'media_filename' => $filename,
                'caption' => $caption !== '' ? $caption : null,
            ],
            MetaWhatsAppMessageStatus::Sent,
            $sender?->name ? 'Media by '.$sender->name : 'Session media reply',
            is_array($result['response'] ?? null) ? $result['response'] : null,
            $student->id,
            ['message_source' => $source->value],
        );

        $this->media->storeOutboundCopy($logged, $file);

        return $result;
    }
}

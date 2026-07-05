<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageStatus;
use App\Models\Student;
use App\Models\User;

class MetaWhatsAppInboxService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppMessageLogger $logger,
        protected StudentWhatsAppThreadService $thread,
        protected WhatsAppProviderResolver $resolver,
    ) {}

    /**
     * @return array{status: string, error?: string, message_id?: string}
     */
    public function sendReply(Student $student, string $text, ?User $sender = null): array
    {
        if (! $this->resolver->metaOverridesPalDigital()) {
            return ['status' => 'failed', 'error' => 'Meta WhatsApp routing is not active.'];
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
            $this->logger->recordOutbound(
                (string) $student->mobile,
                $result['message_id'] ?? null,
                '',
                '',
                [],
                MetaWhatsAppMessageStatus::Sent,
                $sender?->name ? 'Reply by '.$sender->name : 'Session reply',
                is_array($result['response'] ?? null) ? $result['response'] : null,
                $student->id,
                $text,
            );
        }

        return $result;
    }
}

<?php

namespace App\Support;

use Carbon\Carbon;

readonly class MetaWhatsAppConversation
{
    /**
     * @param  list<string>  $contactTags
     * @param  list<array{name: string, label: string}>  $seenBy
     */
    public function __construct(
        public ?int $studentId,
        public string $studentName,
        public string $phone,
        public string $phoneDisplay,
        public string $preview,
        public string $lastDirection,
        public ?Carbon $lastAt,
        public bool $sessionOpen,
        public bool $needsReply,
        public bool $lastSendFailed = false,
        public bool $isLinked = true,
        public string $contactKind = 'unknown',
        public array $contactTags = [],
        public ?int $staffUserId = null,
        public ?int $lastMessageId = null,
        public array $seenBy = [],
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'student_name' => $this->studentName,
            'phone' => $this->phone,
            'phone_display' => $this->phoneDisplay,
            'preview' => $this->preview,
            'last_direction' => $this->lastDirection,
            'last_at' => $this->lastAt?->toIso8601String(),
            'last_at_label' => $this->lastAtLabel(),
            'session_open' => $this->sessionOpen,
            'needs_reply' => $this->needsReply,
            'last_send_failed' => $this->lastSendFailed,
            'is_linked' => $this->isLinked,
            'contact_kind' => $this->contactKind,
            'contact_tags' => $this->contactTags,
            'staff_user_id' => $this->staffUserId,
            'last_message_id' => $this->lastMessageId,
            'seen_by' => $this->seenBy,
        ];
    }

    /**
     * @param  list<array{name: string, label: string}>  $seenBy
     */
    public function withSeenBy(array $seenBy): self
    {
        return new self(
            studentId: $this->studentId,
            studentName: $this->studentName,
            phone: $this->phone,
            phoneDisplay: $this->phoneDisplay,
            preview: $this->preview,
            lastDirection: $this->lastDirection,
            lastAt: $this->lastAt,
            sessionOpen: $this->sessionOpen,
            needsReply: $this->needsReply,
            lastSendFailed: $this->lastSendFailed,
            isLinked: $this->isLinked,
            contactKind: $this->contactKind,
            contactTags: $this->contactTags,
            staffUserId: $this->staffUserId,
            lastMessageId: $this->lastMessageId,
            seenBy: $seenBy,
        );
    }

    public function lastAtLabel(): string
    {
        if ($this->lastAt === null) {
            return '';
        }

        if ($this->lastAt->isToday()) {
            return $this->lastAt->format('g:i A');
        }

        if ($this->lastAt->isYesterday()) {
            return 'Yesterday';
        }

        if ($this->lastAt->greaterThan(now()->subDays(6))) {
            return $this->lastAt->format('D');
        }

        return $this->lastAt->format('d M');
    }
}

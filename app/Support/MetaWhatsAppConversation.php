<?php

namespace App\Support;

use Carbon\Carbon;

readonly class MetaWhatsAppConversation
{
    public function __construct(
        public int $studentId,
        public string $studentName,
        public string $phone,
        public string $phoneDisplay,
        public string $preview,
        public string $lastDirection,
        public ?Carbon $lastAt,
        public bool $sessionOpen,
        public bool $needsReply,
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
        ];
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

<?php

namespace App\Support;

use App\Enums\WhatsAppContactKind;
use App\Models\Student;
use App\Models\User;

readonly class WhatsAppInboxContact
{
    /**
     * @param  list<WhatsAppContactKind>  $tags
     */
    public function __construct(
        public string $displayName,
        public WhatsAppContactKind $kind,
        public array $tags,
        public ?Student $student = null,
        public ?User $staff = null,
    ) {}

    public function isLinked(): bool
    {
        return $this->kind !== WhatsAppContactKind::Unknown;
    }

    /**
     * @return list<string>
     */
    public function tagLabels(): array
    {
        return array_map(
            fn (WhatsAppContactKind $tag): string => $tag->label(),
            $this->tags,
        );
    }

    public static function unknown(): self
    {
        return new self(
            displayName: 'Unknown contact',
            kind: WhatsAppContactKind::Unknown,
            tags: [],
        );
    }
}

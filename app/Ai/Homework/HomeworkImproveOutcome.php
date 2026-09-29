<?php

namespace App\Ai\Homework;

final class HomeworkImproveOutcome
{
    public function __construct(
        public bool $ok,
        public string $title = '',
        public string $description = '',
        public string $message = '',
        public int $usedToday = 0,
        public int $dailyLimit = 0,
        public bool $temporary = false,
    ) {}

    public static function success(string $title, string $description, int $usedToday, int $dailyLimit): self
    {
        return new self(
            ok: true,
            title: $title,
            description: $description,
            usedToday: $usedToday,
            dailyLimit: $dailyLimit,
        );
    }

    public static function failed(string $message, int $usedToday, int $dailyLimit, bool $temporary = false): self
    {
        return new self(
            ok: false,
            message: $message,
            usedToday: $usedToday,
            dailyLimit: $dailyLimit,
            temporary: $temporary,
        );
    }
}

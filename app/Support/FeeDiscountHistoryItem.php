<?php

namespace App\Support;

use Carbon\CarbonInterface;

readonly class FeeDiscountHistoryItem
{
    public function __construct(
        public string $kind,
        public string $kindLabel,
        public string $label,
        public float $amount,
        public string $studentName,
        public ?int $studentId,
        public string $actorName,
        public ?string $reason,
        public CarbonInterface $occurredAt,
        public string $source,
    ) {}
}

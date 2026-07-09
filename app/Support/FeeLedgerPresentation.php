<?php

namespace App\Support;

readonly class FeeLedgerPresentation
{
    public function __construct(
        public string $side,
        public string $sideLabel,
        public string $label,
        public float $amount,
        public ?string $detail = null,
    ) {}
}

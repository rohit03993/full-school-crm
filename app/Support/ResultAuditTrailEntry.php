<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class ResultAuditTrailEntry
{
    public function __construct(
        public string $action,
        public ?Carbon $created_at,
        public string $user_name,
        public ?string $detail = null,
    ) {}
}

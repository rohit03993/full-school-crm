<?php

namespace App\Filament\Concerns;

use App\Support\CrmHint;
use Illuminate\Contracts\Support\Htmlable;

trait ShowsCrmPageHint
{
    protected static function crmHintKey(): ?string
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        $key = static::crmHintKey();

        if (blank($key)) {
            return null;
        }

        return CrmHint::text($key);
    }
}

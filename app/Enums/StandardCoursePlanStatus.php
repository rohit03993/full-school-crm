<?php

namespace App\Enums;

enum StandardCoursePlanStatus: string
{
    case Draft = 'draft';
    case Ready = 'ready';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Ready => 'Ready',
        };
    }
}

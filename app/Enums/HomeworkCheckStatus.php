<?php

namespace App\Enums;

enum HomeworkCheckStatus: string
{
    case Done = 'done';
    case NotDone = 'not_done';

    public function label(): string
    {
        return match ($this) {
            self::Done => 'Done',
            self::NotDone => 'Not Done',
        };
    }
}

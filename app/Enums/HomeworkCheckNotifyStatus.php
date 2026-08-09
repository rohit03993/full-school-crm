<?php

namespace App\Enums;

enum HomeworkCheckNotifyStatus: string
{
    case NotRequired = 'not_required';
    case Pending = 'pending';
    case Sent = 'sent';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::NotRequired => 'Not required',
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Failed => 'Failed',
        };
    }
}

<?php

namespace App\Enums;

enum WhatsAppMessageSource: string
{
    case Campaign = 'campaign';
    case Inbox = 'inbox';
    case Profile = 'profile';
    case Punch = 'punch';
    case Homework = 'homework';
    case PostCall = 'post_call';
    case Automation = 'automation';
    case Test = 'test';

    public function label(): string
    {
        return match ($this) {
            self::Campaign => 'Bulk campaign',
            self::Inbox => 'Inbox',
            self::Profile => 'Student profile',
            self::Punch => 'Attendance alert',
            self::Homework => 'Homework',
            self::PostCall => 'After call',
            self::Automation => 'Automation',
            self::Test => 'Test send',
        };
    }
}

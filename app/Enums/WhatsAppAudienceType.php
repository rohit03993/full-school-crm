<?php

namespace App\Enums;

enum WhatsAppAudienceType: string
{
    case Batch = 'batch';
    case Course = 'course';
    case Leads = 'leads';

    public function label(): string
    {
        return match ($this) {
            self::Batch => 'Single batch',
            self::Course => 'Whole class / course',
            self::Leads => 'Leads (enquiries)',
        };
    }
}

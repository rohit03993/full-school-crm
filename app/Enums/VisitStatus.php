<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Interested = 'interested';
    case FollowUpRequired = 'follow_up_required';
    case AdmissionReady = 'admission_ready';
    case NotInterested = 'not_interested';
    case Joined = 'joined';

    public function label(): string
    {
        return match ($this) {
            self::Interested => 'Interested',
            self::FollowUpRequired => 'Follow-up Required',
            self::AdmissionReady => 'Admission Ready',
            self::NotInterested => 'Not Interested',
            self::Joined => 'Joined',
        };
    }
}

<?php

namespace App\Enums;

enum StandardCoursePracticalKind: string
{
    case Experiment = 'experiment';
    case Activity = 'activity';

    public function label(): string
    {
        return match ($this) {
            self::Experiment => 'Experiment',
            self::Activity => 'Activity',
        };
    }
}

<?php

namespace App\Enums;

enum SeminarType: string
{
    case Interview = 'interview';
    case Motivational = 'motivational';
    case PersonalityDev = 'personality_dev';
    case CareerGuidance = 'career_guidance';

    public function label(): string
    {
        return match ($this) {
            self::Interview => 'Interview Seminar',
            self::Motivational => 'Motivational Seminar',
            self::PersonalityDev => 'Personality Development',
            self::CareerGuidance => 'Career Guidance',
        };
    }
}

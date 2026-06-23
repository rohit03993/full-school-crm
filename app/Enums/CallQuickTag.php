<?php

namespace App\Enums;

enum CallQuickTag: string
{
    case FeeQuery = 'fee_query';
    case CourseInfo = 'course_info';
    case CampusVisit = 'campus_visit';
    case SendBrochure = 'send_brochure';
    case DocumentsPending = 'documents_pending';
    case WillDiscussFamily = 'will_discuss_family';
    case ScholarshipQuery = 'scholarship_query';
    case ReadyToEnroll = 'ready_to_enroll';

    public function label(): string
    {
        return match ($this) {
            self::FeeQuery => 'Fee Query',
            self::CourseInfo => 'Course Info',
            self::CampusVisit => 'Visit',
            self::SendBrochure => 'Brochure',
            self::DocumentsPending => 'Documents',
            self::WillDiscussFamily => 'Family',
            self::ScholarshipQuery => 'Scholarship',
            self::ReadyToEnroll => 'Ready',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $case): array => [$case->value => $case->label()])
            ->all();
    }
}

<?php

namespace App\Enums;

enum InstituteType: string
{
    case School = 'school';
    case Coaching = 'coaching';
    case College = 'college';
    case Hospitality = 'hospitality';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Coaching => 'Coaching Institute',
            self::College => 'College / University',
            self::Hospitality => 'Hotel Management Institute',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::School => 'Class-based programmes, sections, and term exams.',
            self::Coaching => 'Competitive exam batches, mock tests, and test series.',
            self::College => 'Degree programmes, semesters, and internal assessments.',
            self::Hospitality => 'Hotel management programmes, practicals, workshops, and internal assessments.',
        };
    }

    public function programmeLabel(): string
    {
        return match ($this) {
            self::School => 'Class / Programme',
            self::Coaching => 'Coaching Programme',
            self::College => 'Degree / Programme',
            self::Hospitality => 'Hotel Management Programme',
        };
    }

    public function batchLabel(): string
    {
        return match ($this) {
            self::School => 'Class / Section',
            self::Coaching => 'Batch',
            self::College => 'Semester / Section',
            self::Hospitality => 'Batch / Section',
        };
    }

    public function primaryProgrammeCategory(): ProgrammeCategory
    {
        return match ($this) {
            self::School => ProgrammeCategory::School,
            self::Coaching => ProgrammeCategory::Coaching,
            self::College => ProgrammeCategory::College,
            self::Hospitality => ProgrammeCategory::Hospitality,
        };
    }

    public function meetingFor(): MeetingFor
    {
        return match ($this) {
            self::School => MeetingFor::School,
            self::Coaching => MeetingFor::Coaching,
            self::College => MeetingFor::College,
            self::Hospitality => MeetingFor::General,
        };
    }

    /**
     * @return array<int, ProgrammeCategory>
     */
    public function programmeCategories(): array
    {
        return [$this->primaryProgrammeCategory()];
    }
}

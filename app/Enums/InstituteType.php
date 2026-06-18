<?php

namespace App\Enums;

enum InstituteType: string
{
    case School = 'school';
    case Coaching = 'coaching';
    case College = 'college';

    public function label(): string
    {
        return match ($this) {
            self::School => 'School',
            self::Coaching => 'Coaching Institute',
            self::College => 'College / University',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::School => 'Class-based programmes, sections, and term exams.',
            self::Coaching => 'Competitive exam batches, mock tests, and test series.',
            self::College => 'Degree programmes, semesters, and internal assessments.',
        };
    }

    public function programmeLabel(): string
    {
        return match ($this) {
            self::School => 'Class / Programme',
            self::Coaching => 'Coaching Programme',
            self::College => 'Degree / Programme',
        };
    }

    public function batchLabel(): string
    {
        return match ($this) {
            self::School => 'Class / Section',
            self::Coaching => 'Batch',
            self::College => 'Semester / Section',
        };
    }

    public function primaryProgrammeCategory(): ProgrammeCategory
    {
        return match ($this) {
            self::School => ProgrammeCategory::School,
            self::Coaching => ProgrammeCategory::Coaching,
            self::College => ProgrammeCategory::College,
        };
    }

    public function meetingFor(): MeetingFor
    {
        return match ($this) {
            self::School => MeetingFor::School,
            self::Coaching => MeetingFor::Coaching,
            self::College => MeetingFor::College,
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

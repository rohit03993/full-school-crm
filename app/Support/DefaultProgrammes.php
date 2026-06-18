<?php

namespace App\Support;

use App\Enums\DurationType;
use App\Enums\InstituteType;
use App\Enums\ProgrammeCategory;

class DefaultProgrammes
{
    /**
     * Internal system course — hidden from public site and programme lists.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function internal(): array
    {
        return [
            [
                'name' => 'Course Not Decided',
                'code' => 'GEN-UNDECIDED',
                'programme_category' => ProgrammeCategory::Custom,
                'duration' => 1,
                'duration_type' => DurationType::Months,
                'fee' => 0,
                'description' => 'Placeholder for walk-in enquiries when course interest is not yet confirmed.',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function forType(InstituteType $type): array
    {
        return match ($type) {
            InstituteType::School => [
                [
                    'name' => 'Class 11 Science',
                    'code' => 'SCH-11-SCI',
                    'programme_category' => ProgrammeCategory::School,
                    'duration' => 1,
                    'duration_type' => DurationType::Years,
                    'fee' => 100000,
                    'description' => 'Class 11 Science stream — full academic year programme.',
                ],
                [
                    'name' => 'Class 12 Commerce',
                    'code' => 'SCH-12-COM',
                    'programme_category' => ProgrammeCategory::School,
                    'duration' => 1,
                    'duration_type' => DurationType::Years,
                    'fee' => 120000,
                    'description' => 'Class 12 Commerce stream — full academic year programme.',
                ],
            ],
            InstituteType::Coaching => [
                [
                    'name' => 'JEE Foundation',
                    'code' => 'COACH-JEE-1Y',
                    'programme_category' => ProgrammeCategory::Coaching,
                    'duration' => 12,
                    'duration_type' => DurationType::Months,
                    'fee' => 85000,
                    'description' => 'One-year foundation coaching for engineering entrance exams.',
                ],
                [
                    'name' => 'NEET Foundation',
                    'code' => 'COACH-NEET-1Y',
                    'programme_category' => ProgrammeCategory::Coaching,
                    'duration' => 12,
                    'duration_type' => DurationType::Months,
                    'fee' => 90000,
                    'description' => 'One-year foundation coaching for medical entrance exams.',
                ],
            ],
            InstituteType::College => [
                [
                    'name' => 'B.Com Year 2',
                    'code' => 'COL-BCOM-2Y',
                    'programme_category' => ProgrammeCategory::College,
                    'duration' => 3,
                    'duration_type' => DurationType::Years,
                    'fee' => 95000,
                    'description' => 'Second year Bachelor of Commerce — degree programme.',
                ],
                [
                    'name' => 'B.Sc Year 1',
                    'code' => 'COL-BSC-1Y',
                    'programme_category' => ProgrammeCategory::College,
                    'duration' => 3,
                    'duration_type' => DurationType::Years,
                    'fee' => 88000,
                    'description' => 'First year Bachelor of Science — degree programme.',
                ],
            ],
        };
    }
}

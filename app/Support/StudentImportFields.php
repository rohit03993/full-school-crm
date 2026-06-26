<?php

namespace App\Support;

class StudentImportFields
{
    public const ROLL_NUMBER = 'roll_number';

    public const NAME = 'name';

    public const FATHER_NAME = 'father_name';

    public const MOBILE = 'mobile';

    public const DATE_OF_BIRTH = 'date_of_birth';

    public const GENDER = 'gender';

    public const BATCH_SECTION = 'batch_section';

    public const SKIP = 'skip';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ROLL_NUMBER => 'Roll number',
            self::NAME => 'Student name',
            self::FATHER_NAME => "Father's name (optional)",
            self::MOBILE => 'Primary mobile (optional)',
            self::DATE_OF_BIRTH => 'Date of birth (optional)',
            self::GENDER => 'Gender (optional)',
            self::BATCH_SECTION => 'Batch name (from spreadsheet)',
            self::SKIP => 'Skip this column',
        ];
    }

    /**
     * @return list<string>
     */
    public static function required(): array
    {
        return [
            self::ROLL_NUMBER,
            self::NAME,
            self::BATCH_SECTION,
        ];
    }
}

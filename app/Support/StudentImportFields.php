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

    public const ADDRESS = 'address';

    public const CITY = 'city';

    public const STATE = 'state';

    public const PINCODE = 'pincode';

    public const EMAIL = 'email';

    public const ALTERNATE_MOBILE = 'alternate_mobile';

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
            self::ADDRESS => 'Address (optional)',
            self::CITY => 'City (optional)',
            self::STATE => 'State (optional)',
            self::PINCODE => 'Pincode (optional)',
            self::EMAIL => 'Email (optional)',
            self::ALTERNATE_MOBILE => 'Alternate mobile (optional)',
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

    /**
     * Required when all rows are assigned to one CRM batch (no batch column in file).
     *
     * @return list<string>
     */
    public static function requiredWithoutBatchColumn(): array
    {
        return [
            self::ROLL_NUMBER,
            self::NAME,
        ];
    }

    /**
     * Update existing students. Only the roll number is required. Other mapped columns are written when the cell has a value.
     *
     * @return list<string>
     */
    public static function requiredForUpdate(): array
    {
        return [
            self::ROLL_NUMBER,
        ];
    }

    /**
     * @return list<string>
     */
    public static function profileOnly(): array
    {
        return [
            self::ADDRESS,
            self::CITY,
            self::STATE,
            self::PINCODE,
            self::EMAIL,
            self::ALTERNATE_MOBILE,
        ];
    }

    /**
     * @return list<string>
     */
    public static function updatable(): array
    {
        return [
            self::NAME,
            self::FATHER_NAME,
            self::MOBILE,
            self::ALTERNATE_MOBILE,
            self::EMAIL,
            self::DATE_OF_BIRTH,
            self::GENDER,
            self::ADDRESS,
            self::CITY,
            self::STATE,
            self::PINCODE,
        ];
    }
}

<?php

namespace App\Support;

class ActivityMarksImportFields
{
    public const ROLL_NUMBER = 'roll_number';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::ROLL_NUMBER => 'Roll number',
        ];
    }
}

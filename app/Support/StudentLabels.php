<?php

namespace App\Support;

final class StudentLabels
{
    public static function rollNumberLabel(): string
    {
        return InstituteTerminology::label('roll_number');
    }
}

<?php

namespace App\Enums;

enum BatchStaffRole: string
{
    case LeadTeacher = 'lead_teacher';
    case SubjectTeacher = 'subject_teacher';

    public function label(): string
    {
        return match ($this) {
            self::LeadTeacher => 'Class / batch lead',
            self::SubjectTeacher => 'Subject teacher',
        };
    }
}

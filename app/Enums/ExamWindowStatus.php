<?php

namespace App\Enums;

enum ExamWindowStatus: string
{
    case Draft = 'draft';
    case Open = 'open';
    case Submitted = 'submitted';
    case Approved = 'approved';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Open => 'Teachers entering',
            self::Submitted => 'Waiting approval',
            self::Approved => 'Ready to publish',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Open => 'info',
            self::Submitted => 'warning',
            self::Approved => 'success',
        };
    }

    public function allowsTeacherEntry(): bool
    {
        return $this === self::Open;
    }
}

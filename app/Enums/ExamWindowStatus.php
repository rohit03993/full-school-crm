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
            self::Open => 'Open for teachers',
            self::Submitted => 'Submitted for approval',
            self::Approved => 'Approved',
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

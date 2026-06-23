<?php

namespace App\Enums;

enum StudentImportDuplicateResolution: string
{
    case KeepExisting = 'keep_existing';
    case UseFile = 'use_file';

    public function label(): string
    {
        return match ($this) {
            self::KeepExisting => 'Keep existing CRM record (skip row)',
            self::UseFile => 'Use file data (update student & enroll if needed)',
        };
    }
}

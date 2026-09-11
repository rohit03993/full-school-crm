<?php

namespace App\Support;

use App\Filament\Pages\StudentProfilePage;

/**
 * Shared profile URLs for CRM person names (students and leads share StudentProfilePage).
 */
final class CrmPersonLink
{
    public static function studentUrl(?int $studentId): ?string
    {
        if ($studentId === null || $studentId < 1) {
            return null;
        }

        try {
            return StudentProfilePage::getUrl(['record' => $studentId]);
        } catch (\Throwable) {
            return null;
        }
    }
}

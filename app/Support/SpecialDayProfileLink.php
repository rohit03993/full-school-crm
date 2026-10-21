<?php

namespace App\Support;

use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Staff\StaffResource;

class SpecialDayProfileLink
{
    public static function url(?int $studentId, ?int $userId): ?string
    {
        if (filled($studentId) && StudentProfilePage::canAccess()) {
            return StudentProfilePage::getUrl(['record' => $studentId]);
        }

        if (filled($userId) && StaffResource::canAccess()) {
            return StaffResource::getUrl('edit', ['record' => $userId]);
        }

        return null;
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\OccasionWishType;
use App\Filament\Pages\OccasionWishesPage;
use App\Filament\Pages\StudentProfilePage;
use App\Filament\Resources\Staff\StaffResource;
use App\Services\OccasionWishService;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;

class DashboardSpecialDaysWidget extends Widget
{
    protected static bool $isLazy = false;

    protected string $view = 'filament.widgets.dashboard-special-days';

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $groups = [
            'students' => [],
            'staff_birthdays' => [],
            'anniversaries' => [],
        ];

        foreach (app(OccasionWishService::class)->matchesForDate(now()) as $match) {
            $key = match ($match['occasion']) {
                OccasionWishType::StudentBirthday->value => 'students',
                OccasionWishType::StaffBirthday->value => 'staff_birthdays',
                OccasionWishType::StaffAnniversary->value => 'anniversaries',
                default => null,
            };

            if ($key === null) {
                continue;
            }

            $groups[$key][] = [
                'name' => $match['name'],
                'url' => $this->profileUrl($match),
            ];
        }

        foreach ($groups as $key => $people) {
            usort($groups[$key], fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        }

        return [
            'dateLabel' => now()->format('d M Y'),
            'wishesUrl' => OccasionWishesPage::canAccess() ? OccasionWishesPage::getUrl() : null,
            'groups' => [
                [
                    'key' => 'students',
                    'label' => 'Student birthdays',
                    'tone' => 'rose',
                    'people' => $groups['students'],
                ],
                [
                    'key' => 'staff_birthdays',
                    'label' => 'Staff birthdays',
                    'tone' => 'amber',
                    'people' => $groups['staff_birthdays'],
                ],
                [
                    'key' => 'anniversaries',
                    'label' => 'Anniversaries',
                    'tone' => 'violet',
                    'people' => $groups['anniversaries'],
                ],
            ],
        ];
    }

    /**
     * @param  array{student_id: ?int, user_id: ?int}  $match
     */
    private function profileUrl(array $match): ?string
    {
        if (filled($match['student_id']) && StudentProfilePage::canAccess()) {
            return StudentProfilePage::getUrl(['record' => $match['student_id']]);
        }

        if (filled($match['user_id']) && StaffResource::canAccess()) {
            return StaffResource::getUrl('edit', ['record' => $match['user_id']]);
        }

        return null;
    }
}

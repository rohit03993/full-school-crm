<?php

namespace App\Filament\Widgets;

use App\Enums\OccasionWishType;
use App\Filament\Pages\SpecialDaysPage;
use App\Services\OccasionWishService;
use App\Support\SpecialDayProfileLink;
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
                'url' => SpecialDayProfileLink::url($match['student_id'], $match['user_id']),
            ];
        }

        foreach ($groups as $key => $people) {
            usort($groups[$key], fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));
        }

        return [
            'dateLabel' => now()->format('d M Y'),
            'wishesUrl' => SpecialDaysPage::canAccess() ? SpecialDaysPage::getUrl() : null,
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

}

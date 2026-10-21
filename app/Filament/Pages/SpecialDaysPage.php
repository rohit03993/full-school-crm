<?php

namespace App\Filament\Pages;

use App\Enums\OccasionWishType;
use App\Services\OccasionWishService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\SpecialDayProfileLink;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;

class SpecialDaysPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $slug = 'special-days';

    protected static ?string $title = 'Special days';

    protected static ?int $navigationSort = -190;

    protected string $view = 'filament.pages.special-days';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::specialDays();
    }

    public static function canAccess(): bool
    {
        return CrmAccess::hasPanelAccess(Auth::user());
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('special_days');
    }

    /**
     * @return list<array{label: string, is_today: bool, people: list<array{name: string, occasion: string, tone: string, url: ?string}>}>
     */
    public function days(): array
    {
        $start = now()->startOfDay();
        $grouped = [];

        foreach (app(OccasionWishService::class)->upcoming($start, OccasionWishService::UPCOMING_DAYS, Auth::user()) as $match) {
            $grouped[$match['on']][] = [
                'name' => $match['name'],
                'place' => $match['place'],
                'occasion' => OccasionWishType::from($match['occasion'])->label(),
                'tone' => match ($match['occasion']) {
                    OccasionWishType::StaffBirthday->value => 'amber',
                    OccasionWishType::StaffAnniversary->value => 'violet',
                    default => 'rose',
                },
                'url' => SpecialDayProfileLink::url($match['student_id'], $match['user_id']),
            ];
        }

        $days = [];

        foreach ($grouped as $on => $people) {
            $date = Carbon::parse($on)->startOfDay();
            $rank = ['rose' => 0, 'amber' => 1, 'violet' => 2];
            usort($people, function (array $a, array $b) use ($rank): int {
                $byType = ($rank[$a['tone']] ?? 9) <=> ($rank[$b['tone']] ?? 9);

                return $byType !== 0 ? $byType : strcasecmp($a['name'], $b['name']);
            });

            $days[] = [
                'label' => $date->format('l, d M'),
                'is_today' => $date->isSameDay($start),
                'people' => $people,
            ];
        }

        return $days;
    }

    public function rangeLabel(): string
    {
        $start = now()->startOfDay();
        $end = $start->copy()->addDays(OccasionWishService::UPCOMING_DAYS - 1);

        return $start->format('d M').' – '.$end->format('d M Y');
    }
}

<?php

namespace App\Filament\Widgets;

use App\Enums\CrmPermission;
use App\Filament\Widgets\Concerns\VisibleWithCrmPermission;
use App\Filament\Pages\CallQueuePage;
use App\Filament\Pages\CallReportPage;
use App\Filament\Pages\FollowUpsPage;
use App\Filament\Pages\MyLeadsPage;
use App\Services\CallQueueService;
use App\Support\CrmNavBadges;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;

class CallingStatsWidget extends StatsOverviewWidget
{
    use VisibleWithCrmPermission;

    protected static function crmPermissionForWidget(): CrmPermission
    {
        return CrmPermission::DashboardCallingStats;
    }

    protected static ?int $sort = -5;

    protected ?string $heading = 'Calling today';

    protected ?string $description = 'Your telecaller activity';

    protected int | string | array $columnSpan = 'full';

    protected function getColumns(): int
    {
        return 4;
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $staff = Auth::user();

        if (! $staff) {
            return [];
        }

        $callStats = app(CallQueueService::class)->todayStats($staff);
        $dueFollowUps = CrmNavBadges::followUpsDue();
        $uncalled = CrmNavBadges::myLeadsUncalled($staff);

        return [
            Stat::make('Calls today', (string) $callStats['calls_today'])
                ->description("{$callStats['connected_today']} connected")
                ->descriptionIcon(Heroicon::OutlinedPhone)
                ->color('primary')
                ->url(CallReportPage::getUrl()),
            Stat::make('In queue', (string) $callStats['queue_count'])
                ->description('Ready to call now')
                ->descriptionIcon(Heroicon::OutlinedBars3BottomLeft)
                ->color('warning')
                ->url(CallQueuePage::getUrl()),
            Stat::make('Due follow-ups', (string) $dueFollowUps)
                ->description('Visits + call callbacks')
                ->descriptionIcon(Heroicon::OutlinedBellAlert)
                ->color($dueFollowUps > 0 ? 'danger' : 'gray')
                ->url(FollowUpsPage::getUrl()),
            Stat::make('Uncalled', (string) $uncalled)
                ->description('Assigned to call — not yet dialled')
                ->descriptionIcon(Heroicon::OutlinedUserGroup)
                ->color('success')
                ->url(MyLeadsPage::getUrl()),
        ];
    }
}

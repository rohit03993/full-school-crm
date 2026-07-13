<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\FollowUpWorklistService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FollowUpsPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedBellAlert;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 40;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::followUps();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::followUps();
    }

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_LEADS;

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::Enquiries)) {
            return false;
        }

        return CrmAccess::can(Auth::user(), CrmPermission::LeadsViewAssigned);
    }

    public function getSubheading(): ?string
    {
        $viewer = Auth::user();

        if ($viewer && app(FollowUpWorklistService::class)->canViewAllFollowUps($viewer)) {
            return 'All staff follow-ups — each row shows who is responsible so you can follow up with them.';
        }

        return 'Your assigned follow-ups and call callbacks. Open a profile to log the call and schedule the next date.';
    }

    /**
     * @var Collection<int, \App\Models\Visit>
     */
    public Collection $dueVisits;

    /**
     * @var Collection<int, \App\Models\Visit>
     */
    public Collection $upcomingVisits;

    /**
     * @var Collection<int, \App\Models\Student>
     */
    public Collection $dueCallFollowUps;

    /**
     * @var Collection<int, \App\Models\Student>
     */
    public Collection $upcomingCallFollowUps;

    public int $dueVisitsTotal = 0;

    public int $upcomingVisitsTotal = 0;

    public int $dueCallFollowUpsTotal = 0;

    public int $upcomingCallFollowUpsTotal = 0;

    public function mount(FollowUpWorklistService $worklist): void
    {
        $viewer = Auth::user();
        abort_unless($viewer, 403);

        $this->dueVisits = $worklist->dueAndOverdue($viewer);
        $this->upcomingVisits = $worklist->upcoming($viewer);
        $this->dueCallFollowUps = $worklist->dueCallFollowUps($viewer);
        $this->upcomingCallFollowUps = $worklist->upcomingCallFollowUps($viewer);

        $this->dueVisitsTotal = $worklist->dueAndOverdueCount($viewer);
        $this->upcomingVisitsTotal = $worklist->upcomingVisitsCount($viewer);
        $this->dueCallFollowUpsTotal = $worklist->dueCallFollowUpCount($viewer);
        $this->upcomingCallFollowUpsTotal = $worklist->upcomingCallFollowUpsCount($viewer);
    }

    public static function getNavigationBadge(): ?string
    {
        $viewer = Auth::user();

        if (! $viewer) {
            return null;
        }

        $count = app(FollowUpWorklistService::class)->totalDueCount($viewer);

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.follow-ups-worklist')
                ->viewData(fn (): array => [
                    'dueVisits' => $this->dueVisits,
                    'upcomingVisits' => $this->upcomingVisits,
                    'dueCallFollowUps' => $this->dueCallFollowUps,
                    'upcomingCallFollowUps' => $this->upcomingCallFollowUps,
                    'dueVisitsTotal' => $this->dueVisitsTotal,
                    'upcomingVisitsTotal' => $this->upcomingVisitsTotal,
                    'dueCallFollowUpsTotal' => $this->dueCallFollowUpsTotal,
                    'upcomingCallFollowUpsTotal' => $this->upcomingCallFollowUpsTotal,
                    'listLimit' => FollowUpWorklistService::LIST_LIMIT,
                    'worklist' => app(FollowUpWorklistService::class),
                    'canViewAllFollowUps' => Auth::user()
                        ? app(FollowUpWorklistService::class)->canViewAllFollowUps(Auth::user())
                        : false,
                ]),
        ]);
    }
}

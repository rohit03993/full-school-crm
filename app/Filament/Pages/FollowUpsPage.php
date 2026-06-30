<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\FollowUpWorklistService;
use App\Support\CrmAccess;
use App\Support\FeatureGate;
use App\Support\CrmHint;
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

    protected static ?string $navigationLabel = 'Follow-ups';

    protected static ?string $title = 'Follow-ups';

    protected static ?int $navigationSort = 40;

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
        return CrmHint::text('followups');
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
        $this->dueVisits = $worklist->dueAndOverdue();
        $this->upcomingVisits = $worklist->upcoming();
        $this->dueCallFollowUps = $worklist->dueCallFollowUps();
        $this->upcomingCallFollowUps = $worklist->upcomingCallFollowUps();

        $this->dueVisitsTotal = $worklist->dueAndOverdueCount();
        $this->upcomingVisitsTotal = $worklist->upcomingVisitsCount();
        $this->dueCallFollowUpsTotal = $worklist->dueCallFollowUpCount();
        $this->upcomingCallFollowUpsTotal = $worklist->upcomingCallFollowUpsCount();
    }

    public static function getNavigationBadge(): ?string
    {
        $count = app(FollowUpWorklistService::class)->totalDueCount();

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
                ]),
        ]);
    }
}

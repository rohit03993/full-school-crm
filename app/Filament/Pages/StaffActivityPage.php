<?php

namespace App\Filament\Pages;

use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Filament\Pages\StaffActivityDetailPage;
use App\Models\User;
use App\Services\StaffActivityService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class StaffActivityPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = 'My activity';

    protected static ?int $navigationSort = -198;

    public string $range = 'today';

    public ?int $userId = null;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::myActivity();
    }

    public function getTitle(): string
    {
        $subject = $this->subject();

        if ($subject && $subject->id !== Auth::id()) {
            return $subject->name.' · Activity';
        }

        return 'My activity';
    }

    public function getSubheading(): ?string
    {
        return 'What this person did. Today is the default. Tiles follow their access, not the whole school.';
    }

    public static function canAccess(): bool
    {
        return CrmAccess::hasPanelAccess(Auth::user());
    }

    public static function urlFor(User $subject, StaffActivityRange $range = StaffActivityRange::Today): string
    {
        $query = ['range' => $range->value];

        if ($subject->id !== Auth::id()) {
            $query['user'] = $subject->id;
        }

        return static::getUrl().'?'.http_build_query($query);
    }

    public function mount(): void
    {
        $this->range = StaffActivityRange::fromRequest(request()->query('range'))->value;
        $requested = request()->integer('user');
        $this->userId = $requested > 0 ? $requested : null;
        $this->authorizeSubject();
    }

    public function setRange(string $range): void
    {
        $this->range = StaffActivityRange::fromRequest($range)->value;
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.staff-activity')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $subject = $this->subject();
        $range = StaffActivityRange::fromRequest($this->range);

        return [
            'subjectName' => $subject?->name,
            'viewingOther' => $subject && $subject->id !== Auth::id(),
            'range' => $range->value,
            'ranges' => StaffActivityRange::cases(),
            'rangeLabel' => $range->label(),
            'periodLabel' => $this->periodLabel($range),
            'tiles' => $subject
                ? collect(app(StaffActivityService::class)->tiles($subject, $range))
                    ->map(fn (array $tile): array => [
                        ...$tile,
                        'url' => StaffActivityDetailPage::urlFor(
                            $subject,
                            StaffActivityType::from($tile['key']),
                            $range,
                        ),
                    ])
                    ->all()
                : [],
        ];
    }

    protected function subject(): ?User
    {
        if ($this->userId) {
            return User::query()->find($this->userId);
        }

        return Auth::user();
    }

    protected function authorizeSubject(): void
    {
        $subject = $this->subject();
        $viewer = Auth::user();

        abort_unless(
            $subject && app(StaffActivityService::class)->canView($viewer, $subject),
            403,
        );
    }

    protected function periodLabel(StaffActivityRange $range): string
    {
        [$start, $end] = $range->bounds();

        if ($range === StaffActivityRange::Today) {
            return $start->format('l, j F Y');
        }

        return $start->format('j M').' – '.$end->format('j M Y');
    }
}

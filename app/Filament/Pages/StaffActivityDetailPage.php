<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\StaffActivityRange;
use App\Enums\StaffActivityType;
use App\Models\User;
use App\Services\StaffActivityService;
use App\Support\CrmAccess;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Livewire\WithPagination;

class StaffActivityDetailPage extends Page
{
    use WithPagination;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $slug = 'my-activity/{type}';

    public string $type = '';

    public string $range = 'today';

    public ?int $userId = null;

    public function getTitle(): string
    {
        return $this->activityType()?->label() ?? 'Activity';
    }

    public function getSubheading(): ?string
    {
        $subject = $this->subject();
        $range = StaffActivityRange::fromRequest($this->range);

        return trim(($subject?->name ?? '').' · '.$range->label());
    }

    public static function canAccess(): bool
    {
        return CrmAccess::can(Auth::user(), CrmPermission::StaffManage);
    }

    public static function urlFor(User $subject, StaffActivityType $type, StaffActivityRange $range): string
    {
        $query = ['range' => $range->value];

        if ($subject->id !== Auth::id()) {
            $query['user'] = $subject->id;
        }

        return static::getUrl(['type' => $type->value]).'?'.http_build_query($query);
    }

    public function mount(string $type): void
    {
        $this->type = $type;
        $this->range = StaffActivityRange::fromRequest(request()->query('range'))->value;
        $requested = request()->integer('user');
        $this->userId = $requested > 0 ? $requested : null;

        abort_unless($this->activityType() !== null, 404);
        $this->authorizeSubject();
    }

    public function setRange(string $range): void
    {
        $this->range = StaffActivityRange::fromRequest($range)->value;
        $this->resetPage();
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            View::make('filament.pages.partials.staff-activity-detail')
                ->viewData(fn (): array => $this->viewData()),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(): array
    {
        $subject = $this->subject();
        $viewer = Auth::user();
        $type = $this->activityType();
        $range = StaffActivityRange::fromRequest($this->range);
        $service = app(StaffActivityService::class);

        abort_unless($subject && $type && $viewer && $service->visible($subject, $type), 403);

        return [
            'backUrl' => StaffActivityPage::urlFor($subject, $range),
            'range' => $range->value,
            'ranges' => StaffActivityRange::cases(),
            'rows' => $service->rows($viewer, $subject, $type, $range),
        ];
    }

    protected function activityType(): ?StaffActivityType
    {
        return StaffActivityType::tryFrom($this->type);
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
}

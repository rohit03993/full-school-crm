<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Models\OccasionWish;
use App\Services\OccasionWishService;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

class OccasionWishesPage extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCake;

    protected static ?string $slug = 'occasion-wishes';

    protected static ?string $title = 'Today\'s wishes';

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    protected string $view = 'filament.pages.occasion-wishes';

    public string $on = '';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppOccasions();
    }

    public function getSubheading(): ?string
    {
        return CrmHint::text('whatsapp.occasions');
    }

    public function mount(OccasionWishService $wishes): void
    {
        $this->on = now()->toDateString();
        $this->refreshToday($wishes);
    }

    public function updatedOn(): void
    {
        if (! $this->selectedDate()) {
            $this->on = now()->toDateString();
        }

        $this->refreshToday(app(OccasionWishService::class));
    }

    /**
     * @return Collection<int, OccasionWish>
     */
    public function wishes(): Collection
    {
        $date = $this->selectedDate();

        if (! $date || ! Schema::hasTable('occasion_wishes')) {
            return collect();
        }

        return OccasionWish::query()
            ->whereDate('occasion_on', $date->toDateString())
            ->orderBy('recipient_name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{student_birthdays: int, staff_birthdays: int, anniversaries: int, sent: int, failed: int, skipped: int, pending: int}
     */
    public function summary(): array
    {
        $date = $this->selectedDate() ?? now();

        return app(OccasionWishService::class)->summary($date);
    }

    public function wishesEnabled(): bool
    {
        return app(OccasionWishService::class)->enabled();
    }

    public function automationsUrl(): string
    {
        return ManageWhatsAppSettings::getUrl(['automation' => 'birthdays']);
    }

    public function listReady(): bool
    {
        return Schema::hasTable('occasion_wishes');
    }

    private function refreshToday(OccasionWishService $wishes): void
    {
        $date = $this->selectedDate();

        if (! $date || ! $date->isSameDay(now()) || ! Schema::hasTable('occasion_wishes')) {
            return;
        }

        try {
            $wishes->syncForDate($date);
        } catch (\Throwable $exception) {
            Log::warning('Today\'s wishes could not refresh: '.$exception->getMessage());
        }
    }

    private function selectedDate(): ?Carbon
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->on)) {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $this->on)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }
}

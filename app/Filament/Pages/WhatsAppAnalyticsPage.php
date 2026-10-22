<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppPricingCategory;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsAppAnalyticsService;
use App\Support\CrmAccess;
use App\Support\CrmHint;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class WhatsAppAnalyticsPage extends Page
{
    use RequiresCrmPermission;

    protected static bool $shouldRegisterNavigation = false;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::WhatsappOps;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 15;

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::whatsAppUsage();
    }

    public function getTitle(): string
    {
        return CrmMenuLabels::whatsAppUsage();
    }

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    protected string $view = 'filament.pages.whatsapp-analytics';

    public string $datePreset = '30d';

    public ?string $dateFrom = null;

    public ?string $dateTo = null;

    public function mount(): void
    {
        $this->applyPreset('30d');
    }

    public function getSubheading(): ?string
    {
        if (! CrmAccess::canSeeMessageCost(auth()->user())) {
            return 'How many messages were sent in this date range, and whether the CRM log matches Meta.';
        }

        return CrmHint::text('meta_whatsapp.analytics');
    }

    public function updatedDatePreset(string $value): void
    {
        if ($value !== 'custom') {
            $this->applyPreset($value);
        }
    }

    public function updatedDateFrom(): void
    {
        $this->matchPresetFromDates();
    }

    public function updatedDateTo(): void
    {
        $this->matchPresetFromDates();
    }

    public function rangeLabel(): string
    {
        $from = Carbon::parse($this->dateFrom ?: now()->toDateString())->startOfDay();
        $to = Carbon::parse($this->dateTo ?: now()->toDateString())->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to, $from];
        }

        if ($from->isSameDay($to)) {
            return $from->format('l, d M Y');
        }

        return $from->format('d M Y').' to '.$to->format('d M Y');
    }

    protected function matchPresetFromDates(): void
    {
        $today = now()->toDateString();
        $from = (string) $this->dateFrom;
        $to = (string) $this->dateTo;

        $this->datePreset = match (true) {
            $from === $today && $to === $today => 'today',
            $from === now()->subDays(6)->toDateString() && $to === $today => '7d',
            $from === now()->subDays(29)->toDateString() && $to === $today => '30d',
            $from === now()->subDays(89)->toDateString() && $to === $today => '90d',
            $from === now()->subYears(10)->toDateString() && $to === $today => 'all',
            default => 'custom',
        };
    }

    public function applyPreset(string $preset): void
    {
        $this->datePreset = $preset;

        $this->dateTo = now()->toDateString();

        $this->dateFrom = match ($preset) {
            'today' => now()->toDateString(),
            '7d' => now()->subDays(6)->toDateString(),
            '30d' => now()->subDays(29)->toDateString(),
            '90d' => now()->subDays(89)->toDateString(),
            'all' => now()->subYears(10)->toDateString(),
            default => $this->dateFrom ?? now()->subDays(29)->toDateString(),
        };
    }

    public function refreshAnalytics(): void
    {
        if ($this->datePreset !== 'custom') {
            $this->applyPreset($this->datePreset);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function analyticsData(): array
    {
        $from = Carbon::parse($this->dateFrom ?? now()->subDays(29))->startOfDay();
        $to = Carbon::parse($this->dateTo ?? now())->endOfDay();

        return app(WhatsAppAnalyticsService::class)->summary($from, $to);
    }

    public function formatMoney(float $amount, string $currency = 'INR'): string
    {
        if (strtoupper($currency) === 'INR') {
            return '₹'.number_format($amount, 2);
        }

        return number_format($amount, 2).' '.$currency;
    }

    public function categoryLabel(string $category): string
    {
        return WhatsAppPricingCategory::tryFromMeta($category)->label();
    }

    public function categoryNote(string $category): string
    {
        return match (strtoupper($category)) {
            'UTILITY' => 'Homework, fees, attendance, and other school notices.',
            'MARKETING' => 'Greetings and offers, including birthday wishes.',
            'AUTHENTICATION' => 'Login codes.',
            'AUTHENTICATION_INTERNATIONAL' => 'Login codes sent outside India.',
            'SERVICE' => 'A reply while the parent chat is still open. WhatsApp does not charge this.',
            default => 'Other WhatsApp charges.',
        };
    }

    public function sourceLabel(string $source): string
    {
        return WhatsAppMessageSource::tryFrom($source)?->label() ?? ucfirst(str_replace('_', ' ', $source));
    }

    public function campaignViewUrl(int $campaignId): string
    {
        return WhatsAppCampaignResource::getUrl('view', ['record' => $campaignId]);
    }
}

<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppPricingCategory;
use App\Filament\Concerns\RequiresCrmPermission;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Services\WhatsAppAnalyticsService;
use App\Support\CrmHint;
use App\Support\CrmNavigation;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use UnitEnum;

class WhatsAppAnalyticsPage extends Page
{
    use RequiresCrmPermission;

    protected static function requiredCrmPermission(): CrmPermission
    {
        return CrmPermission::MetaWhatsappSettings;
    }

    protected static function requiredLicenseFeature(): ?LicenseFeature
    {
        return LicenseFeature::WhatsApp;
    }

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static ?string $navigationLabel = 'Analytics & cost';

    protected static ?string $title = 'WhatsApp analytics & cost';

    protected static ?int $navigationSort = 15;

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
        return CrmHint::text('meta_whatsapp.analytics');
    }

    public function updatedDatePreset(string $value): void
    {
        if ($value !== 'custom') {
            $this->applyPreset($value);
        }
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

    public function sourceLabel(string $source): string
    {
        return WhatsAppMessageSource::tryFrom($source)?->label() ?? ucfirst(str_replace('_', ' ', $source));
    }

    public function campaignViewUrl(int $campaignId): string
    {
        return WhatsAppCampaignResource::getUrl('view', ['record' => $campaignId]);
    }
}

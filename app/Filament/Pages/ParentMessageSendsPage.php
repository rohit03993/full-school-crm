<?php

namespace App\Filament\Pages;

use App\Enums\CrmPermission;
use App\Enums\LicenseFeature;
use App\Services\ParentMessageSendService;
use App\Support\CrmAccess;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ParentMessageSendsPage extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $slug = 'parent-message-sends';

    protected static ?string $title = 'Parent send report';

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    protected string $view = 'filament.pages.parent-message-sends';

    public string $dateFrom = '';

    public string $dateTo = '';

    public static function getNavigationLabel(): string
    {
        return CrmMenuLabels::parentMessageSends();
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return false;
        }

        $user = Auth::user();

        return CrmAccess::can($user, CrmPermission::HomeworkManage)
            || CrmAccess::can($user, CrmPermission::MarksPublish)
            || CrmAccess::can($user, CrmPermission::WhatsappOps)
            || CrmAccess::can($user, CrmPermission::WhatsappCampaigns);
    }

    public function mount(): void
    {
        $this->dateTo = now()->toDateString();
        $this->dateFrom = now()->subDays(29)->toDateString();
    }

    public function getSubheading(): ?string
    {
        return 'Who clicked Send, and who clicked Resend, for homework and exam marks. Counts only.';
    }

    /**
     * @return array{staff: list<array<string, mixed>>, recent: list<array<string, mixed>>}
     */
    public function report(): array
    {
        $from = Carbon::parse($this->dateFrom ?: now()->subDays(29))->startOfDay();
        $to = Carbon::parse($this->dateTo ?: now())->endOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        $service = app(ParentMessageSendService::class);

        return [
            'staff' => $service->staffReport($from, $to),
            'recent' => $service->recent($from, $to),
        ];
    }
}

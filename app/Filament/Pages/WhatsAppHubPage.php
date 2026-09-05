<?php

namespace App\Filament\Pages;

use App\Enums\LicenseFeature;
use App\Filament\Resources\MetaWhatsAppTemplates\MetaWhatsAppTemplateResource;
use App\Filament\Resources\WhatsAppCampaigns\WhatsAppCampaignResource;
use App\Filament\Resources\WhatsAppLiveCampaigns\WhatsAppLiveCampaignResource;
use App\Support\CrmMenuLabels;
use App\Support\CrmNavigation;
use App\Support\FeatureGate;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

class WhatsAppHubPage extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $title = 'WhatsApp';

    protected static ?string $slug = 'whatsapp-hub';

    protected static ?int $navigationSort = 1;

    protected static string|UnitEnum|null $navigationGroup = CrmNavigation::GROUP_META_WHATSAPP;

    public static function getNavigationLabel(): string
    {
        return 'WhatsApp';
    }

    public static function canAccess(): bool
    {
        if (! FeatureGate::enabled(LicenseFeature::WhatsApp)) {
            return false;
        }

        return WhatsAppInboxPage::canAccess()
            || WhatsAppAnalyticsPage::canAccess()
            || ListMetaWhatsAppMessagesPage::canAccess()
            || ManageMetaWhatsAppSettings::canAccess()
            || ManageWhatsAppSettings::canAccess()
            || MetaWhatsAppTemplateResource::canAccess()
            || WhatsAppCampaignResource::canAccess()
            || WhatsAppLiveCampaignResource::canAccess()
            || ParentFeeNoticesPage::canAccess();
    }

    public function getSubheading(): ?string
    {
        return 'Inbox, campaigns, templates, automations, and Meta setup.';
    }

    public function content(Schema $schema): Schema
    {
        $cards = [];

        if (WhatsAppInboxPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppInbox(),
                'description' => 'Read parent replies and send follow-up messages.',
                'url' => WhatsAppInboxPage::getUrl(),
                'badge' => 'Daily use',
                'tone' => 'primary',
            ];
        }

        if (WhatsAppCampaignResource::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppBulkCampaigns(),
                'description' => 'Send an approved template to a class or course.',
                'url' => WhatsAppCampaignResource::getUrl('index'),
            ];
        }

        if (ParentFeeNoticesPage::canAccess()) {
            $cards[] = [
                'title' => 'Parent fee notices',
                'description' => 'Bulk pending-fee WhatsApp with amount and due date typed per student (no Fees ledger).',
                'url' => ParentFeeNoticesPage::getUrl(),
                'badge' => 'Manual amounts',
                'tone' => 'primary',
            ];
        }

        if (WhatsAppLiveCampaignResource::canAccess()) {
            $cards[] = [
                'title' => 'Live campaigns',
                'description' => 'Named API campaigns used by automations and triggers.',
                'url' => WhatsAppLiveCampaignResource::getUrl('index'),
            ];
        }

        if (MetaWhatsAppTemplateResource::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppTemplates(),
                'description' => 'Create, sync, and manage Meta-approved templates.',
                'url' => MetaWhatsAppTemplateResource::getUrl('index'),
            ];
        }

        if (ManageWhatsAppSettings::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppAutomations(),
                'description' => 'Attendance, staff punch, and homework not-done automations.',
                'url' => ManageWhatsAppSettings::getUrl(),
            ];
        }

        if (WhatsAppAnalyticsPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppUsage(),
                'description' => 'Meta billed cost vs CRM message log coverage.',
                'url' => WhatsAppAnalyticsPage::getUrl(),
            ];
        }

        if (ListMetaWhatsAppMessagesPage::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppMessageLog(),
                'description' => 'Every logged outbound and inbound WhatsApp message.',
                'url' => ListMetaWhatsAppMessagesPage::getUrl(),
            ];
        }

        if (ManageMetaWhatsAppSettings::canAccess()) {
            $cards[] = [
                'title' => CrmMenuLabels::whatsAppSetup(),
                'description' => 'Phone number ID, token, webhook, and connection test.',
                'url' => ManageMetaWhatsAppSettings::getUrl(),
            ];
        }

        return $schema->components([
            View::make('filament.pages.partials.crm-hub')
                ->viewData([
                    'heading' => 'WhatsApp desk',
                    'intro' => 'One entry for messaging. Each card opens the same screen you used before.',
                    'cards' => $cards,
                    'footer' => '<strong class="text-gray-900 dark:text-white">Module note:</strong> If WhatsApp is turned off in the licence, this hub disappears — Fees and Attendance keep working.',
                ]),
        ]);
    }
}

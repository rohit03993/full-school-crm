<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Support\CrmNavigation;

class WhatsAppProviderResolver
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppSettingsService $metaSettings,
        protected PalDigitalWhatsAppService $palDigital,
    ) {}

    public function activeProvider(): ?WhatsAppProvider
    {
        if ($this->metaSettings->isEnabled() && $this->meta->isConfigured()) {
            return WhatsAppProvider::Meta;
        }

        if ($this->palDigital->isConfigured()) {
            return WhatsAppProvider::PalDigital;
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->activeProvider() !== null;
    }

    public function configurationError(): string
    {
        if ($this->metaSettings->isEnabled()) {
            return 'WhatsApp is enabled but not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save this institute\'s Meta credentials.';
        }

        return 'WhatsApp is not configured. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and save Meta credentials, then turn on WhatsApp enabled.';
    }

    public function activeProviderLabel(): string
    {
        return $this->activeProvider()?->label() ?? 'Not configured';
    }

    public function metaOverridesPalDigital(): bool
    {
        return $this->activeProvider() === WhatsAppProvider::Meta;
    }
}

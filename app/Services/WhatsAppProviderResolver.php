<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;

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
            return 'Meta WhatsApp is enabled but not configured. Open Meta WhatsApp → Connection & Setup and save credentials, or turn Meta off to use Pal Digital.';
        }

        return 'WhatsApp is not configured. Open Setup → WhatsApp Settings (Pal Digital) or enable Meta WhatsApp.';
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

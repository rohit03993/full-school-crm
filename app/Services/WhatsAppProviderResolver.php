<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
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
        $menu = CrmNavigation::whatsAppMenu('Connection & Setup');

        if ($this->metaSettings->isEnabled() && ! $this->meta->isConfigured()) {
            return 'WhatsApp is enabled but Meta credentials are incomplete. Open '.$menu.' and save Phone number ID plus access token.';
        }

        if (! $this->metaSettings->isEnabled() && $this->meta->isConfigured()) {
            return 'Meta credentials are saved but WhatsApp routing is off. Open '.$menu.' and turn on WhatsApp enabled, then save.';
        }

        if ($this->palDigital->isConfigured()) {
            return 'Pal Digital API is configured but Meta routing is off. Either turn on WhatsApp enabled in '.$menu.' or use Pal Digital automations only.';
        }

        return 'No WhatsApp provider is active. Open '.$menu.', save Meta credentials, turn on WhatsApp enabled, and sync templates.';
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        $metaEnabled = $this->metaSettings->isEnabled();
        $metaConfigured = $this->meta->isConfigured();
        $palConfigured = $this->palDigital->isConfigured();
        $provider = $this->activeProvider();

        return [
            'active_provider' => $provider?->value,
            'active_provider_label' => $this->activeProviderLabel(),
            'meta_enabled' => $metaEnabled,
            'meta_configured' => $metaConfigured,
            'meta_has_token' => $this->metaSettings->hasStoredAccessToken(),
            'meta_phone_number_id' => filled($this->meta->phoneNumberId()),
            'pal_digital_configured' => $palConfigured,
            'is_configured' => $this->isConfigured(),
            'configuration_error' => $this->isConfigured() ? null : $this->configurationError(),
        ];
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

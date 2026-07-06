<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
use App\Support\CrmNavigation;

class WhatsAppProviderResolver
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppSettingsService $metaSettings,
    ) {}

    public function activeProvider(): ?WhatsAppProvider
    {
        if ($this->isMetaActive()) {
            return WhatsAppProvider::Meta;
        }

        return null;
    }

    public function isConfigured(): bool
    {
        return $this->isMetaActive();
    }

    public function configurationError(): string
    {
        $menu = CrmNavigation::whatsAppMenu('Connection & Setup');

        if ($this->metaSettings->isEnabled() && ! $this->meta->isConfigured()) {
            return 'WhatsApp is enabled but Meta credentials are incomplete. Open '.$menu.' and save Phone number ID plus access token.';
        }

        if (! $this->metaSettings->isEnabled() && $this->meta->isConfigured()) {
            return 'Meta credentials are saved but WhatsApp is off. Open '.$menu.', turn on WhatsApp enabled, then save.';
        }

        return 'WhatsApp is not set up. Open '.$menu.', save Meta credentials, turn on WhatsApp enabled, and sync templates.';
    }

    /**
     * @return array<string, mixed>
     */
    public function diagnostics(): array
    {
        return [
            'active_provider' => $this->activeProvider()?->value,
            'active_provider_label' => $this->activeProviderLabel(),
            'meta_enabled' => $this->metaSettings->isEnabled(),
            'meta_configured' => $this->meta->isConfigured(),
            'meta_has_token' => $this->metaSettings->hasStoredAccessToken(),
            'meta_phone_number_id' => filled($this->meta->phoneNumberId()),
            'is_configured' => $this->isConfigured(),
            'configuration_error' => $this->isConfigured() ? null : $this->configurationError(),
        ];
    }

    public function activeProviderLabel(): string
    {
        return $this->activeProvider()?->label() ?? 'Not configured';
    }

    public function isMetaActive(): bool
    {
        return $this->metaSettings->isEnabled() && $this->meta->isConfigured();
    }

    /** @deprecated Use isMetaActive() */
    public function metaOverridesPalDigital(): bool
    {
        return $this->isMetaActive();
    }
}

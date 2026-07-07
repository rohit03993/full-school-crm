<?php

namespace App\Enums;

enum WhatsAppPricingCategory: string
{
    case Marketing = 'MARKETING';
    case Utility = 'UTILITY';
    case Authentication = 'AUTHENTICATION';
    case Service = 'SERVICE';
    case AuthenticationInternational = 'AUTHENTICATION_INTERNATIONAL';
    case Unknown = 'UNKNOWN';

    public function label(): string
    {
        return match ($this) {
            self::Marketing => 'Marketing',
            self::Utility => 'Utility',
            self::Authentication => 'Authentication',
            self::Service => 'Service',
            self::AuthenticationInternational => 'Authentication (international)',
            self::Unknown => 'Other',
        };
    }

    public static function tryFromMeta(?string $value): self
    {
        if (blank($value)) {
            return self::Unknown;
        }

        $normalized = strtoupper(trim($value));

        return self::tryFrom($normalized) ?? self::Unknown;
    }
}

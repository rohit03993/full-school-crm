<?php

namespace App\Support;

/**
 * WhatsApp Authentication (copy-code) template for staff and student login OTP.
 * Meta rejects OTP/login wording as Utility — submit as AUTHENTICATION from CRM.
 */
final class LoginOtpWhatsAppTemplate
{
    public const NAME = 'login_otp';

    /** @var list<string> */
    public const ALIASES = [
        'login_otp',
        'staff_login_otp',
        'account_login',
        'otp_login',
    ];

    public const CATEGORY = 'AUTHENTICATION';

    public const EXPIRY_MINUTES = 5;

    /** Preview only — Meta uses its own Authentication body + Copy code button. */
    public const BODY = <<<'TXT'
{{1}} is your verification code. For your security, do not share this code. This code expires in 5 minutes.
TXT;

    /**
     * @return array<int, array{label: string, example: string}>
     */
    public static function variables(): array
    {
        return [
            1 => [
                'label' => '4-digit OTP',
                'example' => '4821',
            ],
        ];
    }

    /**
     * @return list<array{index: int, label: string, example: string}>
     */
    public static function sampleRows(): array
    {
        $rows = [];

        foreach (self::variables() as $index => $variable) {
            $rows[] = [
                'index' => $index,
                'label' => $variable['label'],
                'example' => $variable['example'],
            ];
        }

        return $rows;
    }

    public static function looksLikeName(string $name): bool
    {
        $normalized = MetaWhatsAppTemplateBuilder::normalizeName($name);

        return $normalized !== '' && in_array($normalized, self::ALIASES, true);
    }
}

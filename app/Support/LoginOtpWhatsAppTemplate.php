<?php

namespace App\Support;

/**
 * WhatsApp template for staff and student account login OTP.
 * Prefer Meta AUTHENTICATION (copy-code) named login_otp; UTILITY with {{1}} also works.
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

    public const CATEGORY = 'UTILITY';

    public const BODY = <<<'TXT'
Your login code is {{1}}. Do not share this code with anyone. It expires in 5 minutes.
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

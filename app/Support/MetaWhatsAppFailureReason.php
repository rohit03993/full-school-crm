<?php

namespace App\Support;

use App\Models\MetaWhatsAppMessage;

class MetaWhatsAppFailureReason
{
    public const HOLD_MESSAGE = 'WhatsApp already refused a marketing message to this number today. It was not sent again.';

    public static function isMarketingLimit(?string $detail): bool
    {
        $detail = mb_strtolower(trim((string) $detail));

        return $detail !== '' && (
            str_contains($detail, 'healthy ecosystem engagement')
            || str_contains($detail, '131049')
        );
    }

    public static function recentlyLimited(string $phone): bool
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if (strlen($digits) === 10) {
            $digits = '91'.$digits;
        }

        if ($digits === '') {
            return false;
        }

        return MetaWhatsAppMessage::query()
            ->where('direction', 'outbound')
            ->where('status', 'failed')
            ->where('phone', $digits)
            ->where('status_at', '>=', now()->subDay())
            ->where(function ($query): void {
                $query->where('status_detail', 'like', '%healthy ecosystem engagement%')
                    ->orWhere('status_detail', 'like', '%131049%');
            })
            ->exists();
    }

    public static function forMessage(MetaWhatsAppMessage $message): ?string
    {
        if ($message->status !== 'failed') {
            return null;
        }

        if (filled($message->status_detail)) {
            return (string) $message->status_detail;
        }

        $payload = is_array($message->payload) ? $message->payload : [];

        if ($payload === []) {
            return null;
        }

        if (isset($payload['errors']) || array_key_exists('status', $payload)) {
            $fromWebhook = self::fromWebhookStatus($payload);

            return $fromWebhook === 'Delivery failed' ? null : $fromWebhook;
        }

        if (isset($payload['error']) && is_array($payload['error'])) {
            return self::fromApiError($payload['error']);
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $statusRow
     */
    public static function fromWebhookStatus(array $statusRow): string
    {
        $error = data_get($statusRow, 'errors.0');

        if (! is_array($error)) {
            return 'Delivery failed';
        }

        $title = trim((string) ($error['title'] ?? ''));
        $message = trim((string) ($error['message'] ?? ''));
        $details = trim((string) data_get($error, 'error_data.details', ''));
        $code = $error['code'] ?? null;
        $line = $title !== '' ? $title : $message;

        if ($code !== null && $code !== '') {
            $line = trim($line.' (code '.$code.')');
        }

        foreach ([$details, $message] as $extra) {
            if ($extra === '' || ($line !== '' && str_contains(mb_strtolower($line), mb_strtolower($extra)))) {
                continue;
            }

            $line = trim($line.'. '.$extra);
        }

        return mb_substr($line !== '' ? $line : 'Delivery failed', 0, 250);
    }

    /**
     * @param  array<string, mixed>  $error
     */
    public static function fromApiError(array $error): ?string
    {
        $message = trim((string) ($error['message'] ?? ''));
        $details = trim((string) data_get($error, 'error_data.details', ''));
        $code = $error['code'] ?? null;
        $line = $message;

        if ($code !== null && $code !== '' && ! str_contains($line, (string) $code)) {
            $line = trim($line.' (code '.$code.')');
        }

        if ($details !== '' && ($line === '' || ! str_contains(mb_strtolower($line), mb_strtolower($details)))) {
            $line = trim($line.'. '.$details);
        }

        return $line !== '' ? mb_substr($line, 0, 250) : null;
    }
}

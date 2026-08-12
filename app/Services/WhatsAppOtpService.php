<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Support\IndianMobileNumber;
use Illuminate\Support\Facades\Cache;

/**
 * 4-digit login OTP delivered via Meta WhatsApp authentication/utility template.
 * Stores HMAC digests in cache only (no plain OTP in DB).
 */
class WhatsAppOtpService
{
    public const OTP_LENGTH = 4;

    public const RATE_LIMIT_SECONDS = 60;

    public const PURPOSE_STUDENT = 'student';

    public const PURPOSE_STAFF = 'staff';

    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    public function isAvailable(): bool
    {
        return $this->meta->isConfigured()
            && (bool) Setting::getValue('meta_whatsapp.enabled', false)
            && filled($this->otpTemplateName());
    }

    public function otpOnlyEnabled(): bool
    {
        return (bool) Setting::getValue('meta_whatsapp.otp_only_login', false);
    }

    /**
     * Password login is blocked only when OTP-only is on AND OTP can actually send.
     * If WhatsApp is down, password remains as a fallback so the institute is not locked out.
     */
    public function passwordLoginAllowed(): bool
    {
        return ! ($this->otpOnlyEnabled() && $this->isAvailable());
    }

    public function otpTemplateName(): string
    {
        return trim((string) Setting::getValue(
            'meta_whatsapp.otp_template_name',
            config('meta_whatsapp.otp_template_name', ''),
        ));
    }

    public function otpTemplateLanguage(): string
    {
        $approved = $this->approvedOtpTemplate($this->otpTemplateName());
        $synced = trim((string) ($approved?->language ?? ''));

        if ($synced !== '') {
            return $synced;
        }

        $configured = trim((string) Setting::getValue(
            'meta_whatsapp.otp_template_language',
            config('meta_whatsapp.otp_template_language', ''),
        ));

        if ($configured !== '') {
            return $configured;
        }

        return $this->meta->defaultLanguage();
    }

    protected function approvedOtpTemplate(string $name): ?MetaWhatsAppTemplate
    {
        if ($name === '') {
            return null;
        }

        $query = MetaWhatsAppTemplate::query()
            ->where('name', $name)
            ->whereRaw('UPPER(status) = ?', ['APPROVED']);

        $configured = trim((string) Setting::getValue('meta_whatsapp.otp_template_language', ''));

        if ($configured !== '') {
            $match = (clone $query)->where('language', $configured)->first();

            if ($match) {
                return $match;
            }
        }

        return $query->orderBy('language')->first();
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function send(string $mobile, string $purpose, ?string $contactName = null): array
    {
        $digits = IndianMobileNumber::normalize($mobile);

        if ($digits === null) {
            return ['success' => false, 'message' => 'Enter a valid 10-digit mobile number.'];
        }

        if (! in_array($purpose, [self::PURPOSE_STUDENT, self::PURPOSE_STAFF], true)) {
            return ['success' => false, 'message' => 'Invalid OTP purpose.'];
        }

        if (! $this->isAvailable()) {
            return [
                'success' => false,
                'message' => 'WhatsApp OTP login is not configured. Ask admin to enable Meta WhatsApp and set the OTP template name under WhatsApp setup.',
            ];
        }

        $rateKey = $this->rateKey($purpose, $digits);

        if (Cache::has($rateKey)) {
            return ['success' => false, 'message' => 'Please wait a minute before requesting another code.'];
        }

        $otp = $this->generateOtp();
        $ttl = max(60, (int) config('meta_whatsapp.otp_ttl_seconds', 300));
        $pepper = (string) config('meta_whatsapp.otp_pepper', config('app.key'));
        $digest = hash_hmac('sha256', $purpose.':'.$digits.':'.$otp, $pepper);

        Cache::put($this->cacheKey($purpose, $digits), $digest, now()->addSeconds($ttl));
        Cache::put($rateKey, true, now()->addSeconds(self::RATE_LIMIT_SECONDS));

        $send = $this->meta->sendAuthenticationOtp(
            $digits,
            $otp,
            $this->otpTemplateName(),
            $this->otpTemplateLanguage(),
            $contactName,
        );

        if (($send['status'] ?? '') !== 'success') {
            Cache::forget($this->cacheKey($purpose, $digits));
            Cache::forget($rateKey);

            return [
                'success' => false,
                'message' => $send['error'] ?? 'Could not send the OTP via WhatsApp. Please try again.',
            ];
        }

        return [
            'success' => true,
            'message' => 'A 4-digit login code was sent to your WhatsApp.',
        ];
    }

    public function verify(string $mobile, string $purpose, string $otp): bool
    {
        $digits = IndianMobileNumber::normalize($mobile);

        if ($digits === null) {
            return false;
        }

        if (! preg_match('/^\d{'.self::OTP_LENGTH.'}$/', $otp)) {
            return false;
        }

        $key = $this->cacheKey($purpose, $digits);
        $stored = Cache::get($key);

        if (! is_string($stored) || $stored === '') {
            return false;
        }

        $pepper = (string) config('meta_whatsapp.otp_pepper', config('app.key'));
        $candidate = hash_hmac('sha256', $purpose.':'.$digits.':'.$otp, $pepper);

        if (! hash_equals($stored, $candidate)) {
            return false;
        }

        Cache::forget($key);

        return true;
    }

    protected function generateOtp(): string
    {
        $min = 10 ** (self::OTP_LENGTH - 1);
        $max = (10 ** self::OTP_LENGTH) - 1;

        return (string) random_int($min, $max);
    }

    protected function cacheKey(string $purpose, string $digits): string
    {
        return 'whatsapp_otp:v1:'.$purpose.':'.$digits;
    }

    protected function rateKey(string $purpose, string $digits): string
    {
        return 'whatsapp_otp_rate:v1:'.$purpose.':'.$digits;
    }
}

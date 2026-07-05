<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;

class MetaWhatsAppSettingsService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return [
            'phone_number_id' => Setting::getValue('meta_whatsapp.phone_number_id', config('meta_whatsapp.phone_number_id')),
            'waba_id' => Setting::getValue('meta_whatsapp.waba_id', config('meta_whatsapp.waba_id')),
            'access_token' => '',
            'verify_token' => Setting::getValue('meta_whatsapp.verify_token', config('meta_whatsapp.verify_token')),
            'app_secret' => '',
            'default_language' => $this->meta->defaultLanguage(),
            'enabled' => (bool) Setting::getValue('meta_whatsapp.enabled', false),
            'test_phone' => Setting::getValue('meta_whatsapp.test_phone'),
            'test_template_name' => Setting::getValue('meta_whatsapp.test_template_name'),
            'test_template_language' => Setting::getValue('meta_whatsapp.test_template_language', 'en'),
            'test_template_param_1' => Setting::getValue('meta_whatsapp.test_template_param_1'),
            'test_template_param_2' => Setting::getValue('meta_whatsapp.test_template_param_2'),
            'test_template_param_3' => Setting::getValue('meta_whatsapp.test_template_param_3'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string, ignored_invalid_token_field?: bool}
     */
    public function save(array $data): array
    {
        $credentials = $this->saveCredentials($data);

        if (! $credentials['ok']) {
            return $credentials;
        }

        Setting::setValue('meta_whatsapp.default_language', trim((string) ($data['default_language'] ?? 'en')), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.enabled', (bool) ($data['enabled'] ?? false) ? '1' : '0', 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.test_phone', trim((string) ($data['test_phone'] ?? '')), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.test_template_name', trim((string) ($data['test_template_name'] ?? '')), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.test_template_language', trim((string) ($data['test_template_language'] ?? 'en')), 'meta_whatsapp');

        foreach ([1, 2, 3] as $index) {
            $key = 'test_template_param_'.$index;
            Setting::setValue('meta_whatsapp.'.$key, trim((string) ($data[$key] ?? '')), 'meta_whatsapp');
        }

        Setting::flushValueCache();

        return [
            'ok' => true,
            'ignored_invalid_token_field' => (bool) ($credentials['ignored_invalid_token_field'] ?? false),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string, ignored_invalid_token_field?: bool}
     */
    public function saveCredentials(array $data, bool $strictToken = false): array
    {
        $phoneNumberId = trim((string) ($data['phone_number_id'] ?? ''));
        $wabaId = trim((string) ($data['waba_id'] ?? ''));
        $accessToken = trim((string) ($data['access_token'] ?? ''));
        $verifyToken = trim((string) ($data['verify_token'] ?? ''));
        $appSecret = trim((string) ($data['app_secret'] ?? ''));

        if ($phoneNumberId === '') {
            return ['ok' => false, 'message' => 'Phone number ID is required.'];
        }

        Setting::setValue('meta_whatsapp.phone_number_id', $phoneNumberId, 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.waba_id', $wabaId, 'meta_whatsapp');

        if ($verifyToken !== '') {
            Setting::setValue('meta_whatsapp.verify_token', $verifyToken, 'meta_whatsapp');
        }

        $ignoredInvalidToken = false;

        if ($accessToken !== '') {
            if (strlen($accessToken) < 20) {
                if ($strictToken) {
                    return ['ok' => false, 'message' => 'Access token looks too short. Paste the full Meta token.'];
                }

                $ignoredInvalidToken = true;
            } else {
                Setting::setValue('meta_whatsapp.access_token', Crypt::encryptString($accessToken), 'meta_whatsapp');
            }
        } elseif ($strictToken && ! $this->hasStoredAccessToken()) {
            return ['ok' => false, 'message' => 'Access token is required.'];
        }

        if ($appSecret !== '') {
            Setting::setValue('meta_whatsapp.app_secret', Crypt::encryptString($appSecret), 'meta_whatsapp');
        }

        Setting::flushValueCache();

        return ['ok' => true, 'ignored_invalid_token_field' => $ignoredInvalidToken];
    }

    public function hasStoredAccessToken(): bool
    {
        return $this->meta->hasStoredAccessToken() || filled(config('meta_whatsapp.access_token'));
    }

    public function isEnabled(): bool
    {
        return (bool) Setting::getValue('meta_whatsapp.enabled', false);
    }

    public function renderAccessTokenStatus(): HtmlString
    {
        if (! $this->hasStoredAccessToken()) {
            return new HtmlString('<p class="text-sm text-gray-500">No access token saved yet. Paste your Meta token below.</p>');
        }

        $masked = e($this->meta->maskedAccessToken() ?? 'saved');

        return new HtmlString('<p class="text-sm text-success-700 dark:text-success-400">Access token saved: <span class="font-mono">'.$masked.'</span></p>');
    }

    public function renderWebhookInfo(): HtmlString
    {
        $url = e($this->meta->webhookUrl());
        $verify = e($this->meta->verifyToken() ?? '(set a verify token below)');

        return new HtmlString(
            '<div class="space-y-2 text-sm text-gray-600 dark:text-gray-300">'
            .'<p><span class="font-medium text-gray-950 dark:text-white">Callback URL</span><br><code class="text-xs break-all">'.$url.'</code></p>'
            .'<p><span class="font-medium text-gray-950 dark:text-white">Verify token</span><br><code class="text-xs break-all">'.$verify.'</code></p>'
            .'<p class="text-xs text-gray-500">Webhook delivery status is Phase 2. Sending works once credentials and templates are configured.</p>'
            .'</div>'
        );
    }

    public function renderSyncedTemplatesTable(): HtmlString
    {
        $templates = MetaWhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">No Meta templates synced yet. Save credentials, then click <strong>Sync templates</strong>.</p>');
        }

        $rows = $templates->map(function (MetaWhatsAppTemplate $template): string {
            $name = e($template->name);
            $language = e($template->language);
            $status = e($template->status);
            $params = (int) $template->param_count;
            $preview = e(mb_substr((string) ($template->body ?? ''), 0, 120));

            return '<tr class="border-b border-gray-100 dark:border-gray-800">'
                .'<td class="py-2 pr-3 font-medium">'.$name.'</td>'
                .'<td class="py-2 pr-3">'.$language.'</td>'
                .'<td class="py-2 pr-3">'.$status.'</td>'
                .'<td class="py-2 pr-3">'.$params.'</td>'
                .'<td class="py-2 text-xs text-gray-500">'.$preview.'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto"><table class="min-w-full text-sm">'
            .'<thead><tr class="text-left text-xs uppercase text-gray-500">'
            .'<th class="py-2 pr-3">Template</th><th class="py-2 pr-3">Lang</th><th class="py-2 pr-3">Status</th><th class="py-2 pr-3">Params</th><th class="py-2">Preview</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table></div>'
        );
    }

    public function ignoredReplaceTokenNotice(bool $ignored): string
    {
        return $ignored ? ' The new access token field was ignored because it looked invalid — leave blank to keep the saved token.' : '';
    }

    /**
     * @return list<string>
     */
    public function testTemplateParams(): array
    {
        return array_values(array_filter([
            Setting::getValue('meta_whatsapp.test_template_param_1'),
            Setting::getValue('meta_whatsapp.test_template_param_2'),
            Setting::getValue('meta_whatsapp.test_template_param_3'),
        ], fn (mixed $value): bool => filled($value)));
    }
}

<?php

namespace App\Services;

use App\Models\MetaWhatsAppMessage;
use App\Models\MetaWhatsAppTemplate;
use App\Models\Setting;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\HtmlString;

class MetaWhatsAppSettingsService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppDashboardService $dashboard,
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
            'otp_template_name' => Setting::getValue('meta_whatsapp.otp_template_name', config('meta_whatsapp.otp_template_name')),
            'otp_template_language' => Setting::getValue('meta_whatsapp.otp_template_language', config('meta_whatsapp.otp_template_language')),
            'otp_include_button_param' => filter_var(
                Setting::getValue('meta_whatsapp.otp_include_button_param', config('meta_whatsapp.otp_include_button_param', true)),
                FILTER_VALIDATE_BOOLEAN,
            ),
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

        Setting::setValue('meta_whatsapp.otp_template_name', trim((string) ($data['otp_template_name'] ?? '')), 'meta_whatsapp');
        Setting::setValue('meta_whatsapp.otp_template_language', trim((string) ($data['otp_template_language'] ?? '')), 'meta_whatsapp');
        Setting::setValue(
            'meta_whatsapp.otp_include_button_param',
            ! empty($data['otp_include_button_param']) ? '1' : '0',
            'meta_whatsapp',
        );

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

    public function renderDashboardStats(): HtmlString
    {
        $stats = $this->dashboard->stats();

        return new HtmlString(
            '<div class="crm-meta-wa-log__stats">'
            .$this->statCard((string) $stats['total'], 'Logged messages', 'total')
            .$this->statCard((string) $stats['outbound'], 'Sent out', 'out')
            .$this->statCard((string) $stats['inbound'], 'Parent replies', 'in')
            .$this->statCard((string) $stats['delivered'], 'Delivered / read', 'delivered')
            .'</div>'
        );
    }

    protected function statCard(string $value, string $label, string $variant): string
    {
        return '<div class="crm-meta-wa-stat crm-meta-wa-stat--'.e($variant).'">'
            .'<span class="crm-meta-wa-stat__value">'.e($value).'</span>'
            .'<span class="crm-meta-wa-stat__label">'.e($label).'</span></div>';
    }

    public function renderRoutingBanner(): HtmlString
    {
        if (! $this->isEnabled()) {
            return new HtmlString(
                '<p class="text-sm text-gray-500">WhatsApp routing is <strong>off</strong>. Save this institute\'s Meta credentials below and turn on <strong>WhatsApp enabled</strong>.</p>'
            );
        }

        if (! $this->meta->isConfigured()) {
            return new HtmlString(
                '<p class="text-sm text-warning-700 dark:text-warning-400">WhatsApp is enabled but credentials are incomplete — complete Phone number ID and access token, then save.</p>'
            );
        }

        return new HtmlString(
            '<p class="text-sm text-success-700 dark:text-success-400">WhatsApp routing is <strong>active</strong> for this institute via Meta Cloud API. Campaigns, punch, and automations send from this CRM.</p>'
        );
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
            .'<p class="text-xs text-gray-500">Subscribe to <strong>messages</strong> in Meta. Delivery updates (sent, delivered, read) and parent replies appear in Message log.</p>'
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

    public function renderRecentMessagesTable(): HtmlString
    {
        $messages = MetaWhatsAppMessage::query()
            ->with('student:id,name')
            ->latest('id')
            ->limit(15)
            ->get();

        if ($messages->isEmpty()) {
            return new HtmlString('<p class="text-sm text-gray-500">No Meta messages logged yet. Send a test message or configure the webhook in Meta.</p>');
        }

        $rows = $messages->map(function (MetaWhatsAppMessage $message): string {
            $direction = $message->direction === 'inbound' ? 'Parent' : 'School';
            $directionClass = $message->direction === 'inbound' ? 'crm-wa-pill--in' : 'crm-wa-pill--out';
            $phone = e($message->phone);
            $status = e(ucfirst($message->status));
            $preview = e(mb_substr((string) ($message->body_preview ?? ''), 0, 80));
            $time = e($message->created_at?->format('d M H:i') ?? '');
            $student = $message->student
                ? e($message->student->name)
                : '<span class="text-gray-400">—</span>';

            return '<tr class="border-b border-gray-100 dark:border-gray-800">'
                .'<td class="py-2 pr-3 whitespace-nowrap">'.$time.'</td>'
                .'<td class="py-2 pr-3"><span class="crm-wa-pill '.$directionClass.'">'.e($direction).'</span></td>'
                .'<td class="py-2 pr-3">'.$student.'</td>'
                .'<td class="py-2 pr-3 font-mono text-xs">'.$phone.'</td>'
                .'<td class="py-2 pr-3 capitalize">'.$status.'</td>'
                .'<td class="py-2 text-xs text-gray-500">'.$preview.'</td>'
                .'</tr>';
        })->implode('');

        return new HtmlString(
            '<div class="overflow-x-auto rounded-xl border border-gray-200/70 dark:border-white/10"><table class="min-w-full text-sm">'
            .'<thead class="bg-gray-50 dark:bg-white/5"><tr class="text-left text-xs uppercase text-gray-500">'
            .'<th class="px-3 py-2">When</th><th class="px-3 py-2">From</th><th class="px-3 py-2">Student</th><th class="px-3 py-2">Phone</th><th class="px-3 py-2">Status</th><th class="px-3 py-2">Preview</th>'
            .'</tr></thead><tbody class="divide-y divide-gray-100 dark:divide-white/10">'.$rows.'</tbody></table></div>'
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

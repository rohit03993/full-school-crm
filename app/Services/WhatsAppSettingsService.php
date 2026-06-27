<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\WhatsAppTemplate;
use Illuminate\Support\HtmlString;

class WhatsAppSettingsService
{
    public function __construct(
        protected PalDigitalWhatsAppService $whatsapp,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function getFormData(): array
    {
        return [
            'pal_digital_api_key' => '',
            'pal_digital_api_url' => Setting::getValue('pal_digital.api_url', config('services.pal_digital.api_url')),
            'postcall_autosend_enabled' => (bool) Setting::getValue('whatsapp.postcall_autosend_enabled', false),
            'postcall_autosend_template_id' => Setting::getValue('whatsapp.postcall_autosend_template_id'),
            'attendance_autosend_enabled' => (bool) Setting::getValue('whatsapp.attendance_autosend_enabled', false),
            'attendance_autosend_template_id' => Setting::getValue('whatsapp.attendance_autosend_template_id'),
            'campaign_batch_size' => (int) Setting::getValue('whatsapp.campaign_batch_size', config('whatsapp.batch_size', 10)),
            'campaign_batch_delay_seconds' => (int) Setting::getValue(
                'whatsapp.campaign_next_batch_delay_seconds',
                config('whatsapp.next_batch_delay_seconds', 2),
            ),
        ];
    }

    public function hasStoredApiKey(): bool
    {
        return $this->whatsapp->hasStoredApiKey();
    }

    public function hasValidStoredApiKey(): bool
    {
        return $this->whatsapp->hasValidIntegrationKey();
    }

    public function maskedStoredApiKey(): ?string
    {
        if (! $this->hasStoredApiKey()) {
            return null;
        }

        return $this->whatsapp->maskedApiKey();
    }

    /**
     * @return 'missing'|'invalid'|'valid'
     */
    public function apiKeyStatus(): string
    {
        if (! $this->hasStoredApiKey()) {
            return 'missing';
        }

        return $this->hasValidStoredApiKey() ? 'valid' : 'invalid';
    }

    public function renderApiKeyStatus(): HtmlString
    {
        $status = $this->apiKeyStatus();
        $masked = $this->maskedStoredApiKey();

        return match ($status) {
            'valid' => new HtmlString(
                '<div class="rounded-lg border border-success-200 bg-success-50 p-3 dark:border-success-500/30 dark:bg-success-500/10">'
                .'<p class="text-sm font-medium text-success-700 dark:text-success-300">Integration key saved</p>'
                .'<p class="mt-1 text-sm text-success-600 dark:text-success-400">'
                .'Stored as <code class="rounded bg-white/60 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">'.e($masked).'</code>'
                .' — persists after logout. Leave the field below blank unless replacing it.'
                .'</p></div>'
            ),
            'invalid' => new HtmlString(
                '<div class="rounded-lg border border-warning-200 bg-warning-50 p-3 dark:border-warning-500/30 dark:bg-warning-500/10">'
                .'<p class="text-sm font-medium text-warning-700 dark:text-warning-300">Invalid integration key on file</p>'
                .'<p class="mt-1 text-sm text-warning-600 dark:text-warning-400">'
                .($masked
                    ? 'Current value <code class="rounded bg-white/60 px-1.5 py-0.5 font-mono text-xs dark:bg-black/20">'.e($masked).'</code> is not a waservice key. '
                    : '')
                .'Paste a valid <strong>wsk.&lt;uuid&gt;.&lt;secret&gt;</strong> key below and click Save settings.'
                .'</p></div>'
            ),
            default => new HtmlString(
                '<div class="rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5">'
                .'<p class="text-sm text-gray-600 dark:text-gray-300">No integration key saved yet. Paste your waservice key below and click Save settings.</p>'
                .'</div>'
            ),
        };
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string, ignored_invalid_key_field?: bool}
     */
    public function saveCredentials(array $data, bool $strictKey = true): array
    {
        $newKey = trim((string) ($data['pal_digital_api_key'] ?? ''));
        $ignoredInvalidKeyField = false;

        if ($newKey !== '') {
            if (! str_starts_with($newKey, 'wsk.')) {
                if (! $strictKey && $this->hasValidStoredApiKey()) {
                    $ignoredInvalidKeyField = true;
                } else {
                    return [
                        'ok' => false,
                        'message' => 'Integration key must start with wsk. (from Pal Digital → Integrations). '
                            .'Clear the replace-key field if you only want to sync — your saved key is already stored.',
                    ];
                }
            } else {
                Setting::setValue('pal_digital.api_key', $newKey, 'whatsapp');
            }
        }

        $url = trim((string) ($data['pal_digital_api_url'] ?? ''));

        if ($url === '' && ! $this->hasStoredApiKey()) {
            return [
                'ok' => false,
                'message' => 'Send API URL is required.',
            ];
        }

        if ($url !== '') {
            Setting::setValue('pal_digital.api_url', $url, 'whatsapp');
        }

        return [
            'ok' => true,
            'ignored_invalid_key_field' => $ignoredInvalidKeyField,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{ok: bool, message?: string, ignored_invalid_key_field?: bool}
     */
    public function save(array $data): array
    {
        $credentials = $this->saveCredentials(
            $data,
            strictKey: ! $this->hasValidStoredApiKey(),
        );

        if (! $credentials['ok']) {
            return $credentials;
        }

        Setting::setValue(
            'whatsapp.postcall_autosend_enabled',
            ! empty($data['postcall_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.postcall_autosend_template_id',
            filled($data['postcall_autosend_template_id'] ?? null) ? (string) $data['postcall_autosend_template_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.attendance_autosend_enabled',
            ! empty($data['attendance_autosend_enabled']) ? '1' : '0',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.attendance_autosend_template_id',
            filled($data['attendance_autosend_template_id'] ?? null) ? (string) $data['attendance_autosend_template_id'] : '',
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.campaign_batch_size',
            (string) max(1, min(50, (int) ($data['campaign_batch_size'] ?? 10))),
            'whatsapp',
        );
        Setting::setValue(
            'whatsapp.campaign_next_batch_delay_seconds',
            (string) max(0, min(60, (int) ($data['campaign_batch_delay_seconds'] ?? 2))),
            'whatsapp',
        );

        return [
            'ok' => true,
            'ignored_invalid_key_field' => (bool) ($credentials['ignored_invalid_key_field'] ?? false),
        ];
    }

    public function ignoredReplaceKeyNotice(bool $ignored): string
    {
        if (! $ignored) {
            return '';
        }

        return ' The replace-key box had invalid text (not a wsk. key) — it was cleared and your saved key was kept.';
    }

    /**
     * @return array<int, string>
     */
    public function templateOptions(): array
    {
        return WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    public function renderSyncedTemplatesTable(): HtmlString
    {
        $templates = WhatsAppTemplate::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        if ($templates->isEmpty()) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">No templates synced yet. Click <strong>Sync templates</strong> after saving your API key.</p>'
            );
        }

        $cards = $templates->map(function (WhatsAppTemplate $template): string {
            $variables = data_get($template->provider_meta, 'body_variables', []);
            $variablesHtml = is_array($variables) && $variables !== []
                ? collect($variables)
                    ->map(fn (mixed $variable, int $index): string => '<span class="inline-flex items-center rounded-md bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700 dark:bg-white/10 dark:text-gray-200">'
                        .e('{{'.($index + 1).'}} '.(string) $variable)
                        .'</span>')
                    ->implode(' ')
                : '<span class="text-xs text-gray-500 dark:text-gray-400">No parameters</span>';

            $mappingSources = $template->paramSources();
            $mappingsHtml = $mappingSources !== []
                ? collect($mappingSources)
                    ->map(function (?string $source, int $index) use ($variables): string {
                        $label = is_array($variables) && filled($variables[$index] ?? null)
                            ? (string) $variables[$index]
                            : 'param '.($index + 1);
                        $mapped = filled($source)
                            ? e($source)
                            : '<span class="text-danger-600 dark:text-danger-400">not mapped</span>';

                        return '<div class="text-xs text-gray-600 dark:text-gray-300"><strong>'.e($label).'</strong> → '.$mapped.'</div>';
                    })
                    ->implode('')
                : '';

            $body = filled($template->body)
                ? '<div class="mt-3 rounded-lg border border-gray-200 bg-gray-50 p-3 text-sm leading-relaxed text-gray-800 whitespace-pre-wrap dark:border-white/10 dark:bg-white/5 dark:text-gray-100">'
                    .e($template->body)
                    .'</div>'
                : '<p class="mt-3 text-sm text-gray-500 dark:text-gray-400">No preview text returned from Pal Digital for this campaign.</p>';

            $syncedAt = $template->synced_at?->format('d M Y') ?? '—';
            $paramLabel = $template->param_count.' param'.($template->param_count === 1 ? '' : 's');

            return '<div class="rounded-xl border border-gray-200 p-4 dark:border-white/10">'
                .'<div class="flex flex-wrap items-start justify-between gap-2">'
                .'<div><h4 class="text-base font-semibold text-gray-950 dark:text-white">'.e($template->name).'</h4>'
                .'<p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Synced '.$syncedAt.'</p></div>'
                .'<span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">'
                .$paramLabel
                .'</span></div>'
                .'<div class="mt-3 flex flex-wrap gap-2">'.$variablesHtml.'</div>'
                .($mappingsHtml !== ''
                    ? '<div class="mt-3 space-y-1 rounded-lg border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-black/20">'.$mappingsHtml.'</div>'
                    : '')
                .$body
                .'</div>';
        })->implode('');

        return new HtmlString(
            '<div class="grid gap-4">'.$cards.'</div>'
        );
    }
}

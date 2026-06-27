<?php

namespace App\Services;

use App\Models\WhatsAppTemplate;
use App\Support\WhatsAppTemplateParamMappingInferrer;

class PalDigitalTemplateSyncService
{
    public function __construct(
        protected PalDigitalWhatsAppService $whatsapp,
    ) {}

    /**
     * Pull live API campaigns from waservice and upsert local WhatsApp templates.
     *
     * @return array{
     *     status: string,
     *     synced: int,
     *     skipped: int,
     *     removed: int,
     *     errors: list<string>,
     *     message: string
     * }
     */
    public function sync(): array
    {
        if (! $this->whatsapp->isConfigured()) {
            return $this->failed('Configure API key and URL first.');
        }

        if (! $this->whatsapp->isWaserviceIntegrationKey()) {
            return $this->failed(
                'Use a waservice integration key (wsk.<uuid>.<secret>) from Pal Digital → Integrations. '
                .'Old AiSensy JWT or login passwords will not work.',
            );
        }

        $result = $this->whatsapp->fetchApiCampaigns();

        if ($result['status'] !== 'success') {
            return $this->failed((string) ($result['error'] ?? 'Could not fetch campaigns from waservice.'));
        }

        $items = $result['items'] ?? [];

        if ($items === []) {
            return $this->failed(
                'No live API campaigns found. Create API campaigns in Pal Digital and set them Live, then sync again.',
            );
        }

        $synced = 0;
        $skipped = 0;
        $errors = [];

        $syncedNames = [];

        foreach ($items as $item) {
            if (! is_array($item)) {
                $skipped++;
                continue;
            }

            try {
                $this->upsertFromApiCampaign($item);
                $syncedNames[] = trim((string) ($item['name'] ?? ''));
                $synced++;
            } catch (\InvalidArgumentException $e) {
                $name = (string) ($item['name'] ?? 'unknown');
                $errors[] = $name.': '.$e->getMessage();
                $skipped++;
            }
        }

        $removed = $this->removeStaleLocalTemplates($syncedNames);

        $message = $synced > 0
            ? "{$synced} template(s) synced from Pal Digital."
            : 'No templates were synced.';

        if ($removed > 0) {
            $message .= " {$removed} old local template(s) removed.";
        }

        if ($errors !== []) {
            $message .= ' '.count($errors).' skipped.';
        }

        return [
            'status' => $synced > 0 ? 'success' : 'failed',
            'synced' => $synced,
            'skipped' => $skipped,
            'removed' => $removed,
            'errors' => $errors,
            'message' => $message,
        ];
    }

    /**
     * @param  array<string, mixed>  $item
     */
    protected function upsertFromApiCampaign(array $item): void
    {
        $name = trim((string) ($item['name'] ?? ''));

        if ($name === '') {
            throw new \InvalidArgumentException('Campaign name is empty.');
        }

        if (strlen($name) > 120) {
            throw new \InvalidArgumentException('Campaign name is too long (max 120 characters).');
        }

        $bodyVariables = $item['body_variables'] ?? [];
        $bodyVariables = is_array($bodyVariables) ? array_values($bodyVariables) : [];

        $paramCount = (int) ($item['param_count'] ?? count($bodyVariables));
        $paramCount = max($paramCount, count($bodyVariables));

        $existing = WhatsAppTemplate::query()->where('name', $name)->first();

        $attributes = [
            'param_count' => $paramCount,
            'body' => filled($item['preview_text'] ?? null) ? (string) $item['preview_text'] : null,
            'synced_at' => now(),
            'provider_meta' => [
                'source' => 'waservice_api_sync',
                'campaign_id' => $item['id'] ?? null,
                'template_name' => $item['template_name'] ?? null,
                'template_language' => $item['template_language'] ?? null,
                'body_variables' => $bodyVariables,
                'status' => $item['status'] ?? null,
            ],
            'is_active' => true,
        ];

        $previousSource = data_get($existing?->provider_meta, 'source');

        if (! $existing || in_array($previousSource, ['waservice_manual_register', 'waservice_api_sync'], true)) {
            $inferred = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount);

            if ($existing && is_array($existing->param_mappings)) {
                foreach ($inferred as $index => $source) {
                    if ($source === null && filled($existing->param_mappings[$index] ?? null)) {
                        $inferred[$index] = $existing->param_mappings[$index];
                    }
                }
            }

            $attributes['param_mappings'] = $inferred;
        }

        if (! $existing) {
            $attributes['description'] = 'Synced from Pal Digital live API campaign';
        }

        WhatsAppTemplate::query()->updateOrCreate(['name' => $name], $attributes);

        WhatsAppTemplate::query()->where('name', $name)->first()?->ensureParamMappings();
    }

    /**
     * Remove CRM-only templates that are not in the latest Pal Digital sync.
     *
     * @param  list<string>  $syncedNames
     */
    protected function removeStaleLocalTemplates(array $syncedNames): int
    {
        $syncedNames = collect($syncedNames)
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($syncedNames === []) {
            return 0;
        }

        $removed = 0;

        WhatsAppTemplate::query()
            ->withCount('campaigns')
            ->get()
            ->each(function (WhatsAppTemplate $template) use ($syncedNames, &$removed): void {
                $source = data_get($template->provider_meta, 'source');
                $stale = ! in_array($template->name, $syncedNames, true)
                    || in_array($source, [null, 'waservice_manual_register'], true);

                if (! $stale) {
                    return;
                }

                if ($template->campaigns_count > 0) {
                    if ($template->is_active) {
                        $template->update(['is_active' => false]);
                        $removed++;
                    }

                    return;
                }

                $template->delete();
                $removed++;
            });

        return $removed;
    }

    /**
     * @return array{
     *     status: string,
     *     synced: int,
     *     skipped: int,
     *     removed: int,
     *     errors: list<string>,
     *     message: string
     * }
     */
    protected function failed(string $message): array
    {
        return [
            'status' => 'failed',
            'synced' => 0,
            'skipped' => 0,
            'removed' => 0,
            'errors' => [$message],
            'message' => $message,
        ];
    }
}

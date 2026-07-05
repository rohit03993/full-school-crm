<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;

class MetaWhatsAppTemplateSyncService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return array{status: string, synced: int, message: string}
     */
    public function sync(): array
    {
        $result = $this->meta->fetchTemplates();

        if ($result['status'] !== 'success') {
            return [
                'status' => 'failed',
                'synced' => 0,
                'message' => (string) ($result['error'] ?? 'Could not fetch templates from Meta.'),
            ];
        }

        $synced = 0;

        foreach ($result['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $status = strtoupper((string) ($item['status'] ?? ''));

            if ($status !== 'APPROVED') {
                continue;
            }

            $name = trim((string) ($item['name'] ?? ''));

            if ($name === '') {
                continue;
            }

            $language = trim((string) ($item['language'] ?? 'en'));
            $components = is_array($item['components'] ?? null) ? $item['components'] : [];
            $parsed = MetaWhatsAppTemplateParser::parse($components);
            $bodyVariables = $parsed['body_variables'];
            $paramCount = (int) $parsed['param_count'];
            $inferredMappings = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount);

            MetaWhatsAppTemplate::query()->updateOrCreate(
                ['name' => $name, 'language' => $language],
                [
                    'status' => $status,
                    'param_count' => $paramCount,
                    'body' => $parsed['body'],
                    'components' => $components,
                    'provider_meta' => [
                        'body_variables' => $bodyVariables,
                        'category' => $item['category'] ?? null,
                        'id' => $item['id'] ?? null,
                    ],
                    'param_mappings' => $inferredMappings,
                    'is_active' => true,
                    'synced_at' => now(),
                ],
            );

            $synced++;
        }

        return [
            'status' => $synced > 0 ? 'success' : 'warning',
            'synced' => $synced,
            'message' => $synced > 0
                ? 'Approved Meta templates are ready under Meta WhatsApp. Pal Digital settings are unchanged.'
                : 'No approved templates returned from Meta. Create and approve templates in Meta Business Manager first.',
        ];
    }
}

<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;

class MetaWhatsAppTemplateSyncService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @return array{status: string, synced: int, approved: int, message: string}
     */
    public function sync(): array
    {
        $result = $this->meta->fetchTemplates();

        if ($result['status'] !== 'success') {
            return [
                'status' => 'failed',
                'synced' => 0,
                'approved' => 0,
                'message' => (string) ($result['error'] ?? 'Could not fetch templates from Meta.'),
            ];
        }

        $synced = 0;
        $approved = 0;

        foreach ($result['items'] ?? [] as $item) {
            if (! is_array($item)) {
                continue;
            }

            $status = strtoupper((string) ($item['status'] ?? ''));

            if ($status === '') {
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

            $existing = MetaWhatsAppTemplate::query()
                ->where('name', $name)
                ->where('language', $language)
                ->first();

            $inferredMappings = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount);
            $paramMappings = $this->resolveParamMappings($existing, $inferredMappings, $paramCount);

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
                        'rejected_reason' => $item['rejected_reason'] ?? null,
                    ],
                    'param_mappings' => $paramMappings,
                    'is_active' => $status === 'APPROVED',
                    'synced_at' => now(),
                ],
            );

            $synced++;

            if ($status === 'APPROVED') {
                $approved++;

                WhatsAppTemplate::query()->updateOrCreate(
                    ['name' => $name],
                    [
                        'description' => 'Synced from Meta ('.$language.')',
                        'param_count' => $paramCount,
                        'body' => $parsed['body'],
                        'param_mappings' => $paramMappings,
                        'provider_meta' => [
                            'body_variables' => $bodyVariables,
                            'meta_language' => $language,
                            'source' => 'meta',
                        ],
                        'is_active' => true,
                        'synced_at' => now(),
                    ],
                );
            } else {
                WhatsAppTemplate::query()
                    ->where('name', $name)
                    ->update(['is_active' => false]);
            }
        }

        if ($synced === 0) {
            return [
                'status' => 'warning',
                'synced' => 0,
                'approved' => 0,
                'message' => 'No templates returned from Meta. Create a template here or in Meta Business Manager, then sync again.',
            ];
        }

        return [
            'status' => 'success',
            'synced' => $synced,
            'approved' => $approved,
            'message' => "Synced {$synced} template(s) from Meta ({$approved} approved). Pending templates appear in the list but cannot be sent until Meta approves them.",
        ];
    }

    /**
     * @param  list<string|null>  $inferredMappings
     * @return list<string|null>
     */
    protected function resolveParamMappings(?MetaWhatsAppTemplate $existing, array $inferredMappings, int $paramCount): array
    {
        if ($paramCount < 1) {
            return [];
        }

        $stored = $existing?->param_mappings ?? [];
        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $storedValue = $stored[$i] ?? null;
            $sources[] = filled($storedValue) ? (string) $storedValue : ($inferredMappings[$i] ?? null);
        }

        return $sources;
    }
}

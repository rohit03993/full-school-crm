<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use InvalidArgumentException;

class MetaWhatsAppTemplateSubmitService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function submit(array $data): MetaWhatsAppTemplate
    {
        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            (string) ($data['name'] ?? ''),
            (string) ($data['language'] ?? 'en'),
            (string) ($data['category'] ?? 'UTILITY'),
            (string) ($data['body_text'] ?? ''),
            filled($data['header_text'] ?? null) ? (string) $data['header_text'] : null,
            filled($data['footer_text'] ?? null) ? (string) $data['footer_text'] : null,
            filled($data['body_examples_csv'] ?? null) ? (string) $data['body_examples_csv'] : null,
            (bool) ($data['allow_category_change'] ?? true),
        );

        $result = $this->meta->createMessageTemplate($payload);

        if ($result['status'] !== 'success') {
            throw new InvalidArgumentException((string) ($result['error'] ?? 'Meta rejected the template.'));
        }

        $metaResponse = is_array($result['data'] ?? null) ? $result['data'] : [];
        $components = $payload['components'];
        $parsed = MetaWhatsAppTemplateParser::parse($components);
        $paramCount = (int) $parsed['param_count'];
        $bodyVariables = $parsed['body_variables'];
        $name = (string) $payload['name'];
        $inferredMappings = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount, $name);

        $language = (string) $payload['language'];
        $status = strtoupper((string) ($metaResponse['status'] ?? 'PENDING'));

        $template = MetaWhatsAppTemplate::query()->updateOrCreate(
            ['name' => $name, 'language' => $language],
            [
                'status' => $status,
                'param_count' => $paramCount,
                'param_mappings' => $inferredMappings,
                'body' => $parsed['body'],
                'components' => $components,
                'provider_meta' => [
                    'body_variables' => $bodyVariables,
                    'category' => $metaResponse['category'] ?? $payload['category'],
                    'id' => $metaResponse['id'] ?? null,
                    'source' => 'crm_submit',
                ],
                'is_active' => $status === 'APPROVED',
                'synced_at' => now(),
            ],
        );

        if ($status === 'APPROVED') {
            $this->mirrorToWhatsAppTemplate($template);
        }

        return $template;
    }

    protected function mirrorToWhatsAppTemplate(MetaWhatsAppTemplate $template): void
    {
        WhatsAppTemplate::query()->updateOrCreate(
            ['name' => $template->name],
            [
                'description' => 'Synced from Meta ('.$template->language.')',
                'param_count' => (int) $template->param_count,
                'body' => $template->body,
                'param_mappings' => $template->param_mappings,
                'provider_meta' => array_merge(
                    $template->provider_meta ?? [],
                    ['meta_language' => $template->language, 'source' => 'meta'],
                ),
                'is_active' => true,
                'synced_at' => now(),
            ],
        );
    }
}

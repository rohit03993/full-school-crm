<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use InvalidArgumentException;

class MetaWhatsAppTemplateUpdateService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
    ) {}

    /**
     * Send new message words to WhatsApp. The template name stays the same.
     * WhatsApp must approve the words again before this template can be sent.
     *
     * @param  list<string>  $examples
     * @param  list<string|null>  $paramMappings
     */
    public function reviseWording(MetaWhatsAppTemplate $template, string $bodyText, array $examples, array $paramMappings): MetaWhatsAppTemplate
    {
        $lockReason = $template->wordingLockReason();

        if ($lockReason !== null) {
            throw new InvalidArgumentException($lockReason);
        }

        $bodyText = trim($bodyText);

        if ($bodyText === trim((string) $template->body)) {
            throw new InvalidArgumentException('The message words are the same, so nothing was sent to WhatsApp.');
        }

        $components = MetaWhatsAppTemplateBuilder::componentsWithReplacedBody(
            is_array($template->components) ? $template->components : [],
            $bodyText,
            $examples,
        );

        $resolved = $this->meta->resolveMessageTemplateId(
            (string) $template->name,
            (string) $template->language,
            data_get($template->provider_meta, 'id'),
        );

        if ($resolved['status'] !== 'success' || blank($resolved['id'] ?? null)) {
            throw new InvalidArgumentException((string) ($resolved['error'] ?? 'Click Sync from Meta, then try again.'));
        }

        $templateId = (string) $resolved['id'];
        $result = $this->meta->updateMessageTemplate($templateId, $components);

        if ($result['status'] !== 'success') {
            throw new InvalidArgumentException((string) ($result['error'] ?? 'WhatsApp rejected the new words. The old message is still in place.'));
        }

        $response = is_array($result['data'] ?? null) ? $result['data'] : [];
        $status = strtoupper((string) ($response['status'] ?? ''));

        if ($status === '' || $status === 'SUCCESS') {
            $status = 'PENDING';
        }

        $parsed = MetaWhatsAppTemplateParser::parse($components);
        $paramCount = (int) $parsed['param_count'];
        $mappings = $this->mappingsFor($template, $paramMappings, $parsed['body_variables'], $paramCount);
        $approved = $status === 'APPROVED';
        $provider = $template->provider_meta ?? [];
        $provider['body_variables'] = $parsed['body_variables'];
        $provider['id'] = $templateId;
        $provider['source'] = 'crm_update';
        $provider['header_format'] = MetaWhatsAppTemplateParser::headerFormat($components);

        $template->update([
            'status' => $status,
            'param_count' => $paramCount,
            'param_mappings' => $mappings,
            'body' => $parsed['body'],
            'components' => $components,
            'provider_meta' => $provider,
            'is_active' => $approved,
            'synced_at' => now(),
        ]);

        $local = WhatsAppTemplate::query()->where('name', $template->name)->first();

        if ($local) {
            $local->update([
                'body' => $parsed['body'],
                'param_count' => $paramCount,
                'param_mappings' => $mappings,
                'is_active' => $approved,
                'synced_at' => now(),
            ]);
        }

        return $template->refresh();
    }

    /**
     * @param  list<string|null>  $paramMappings
     * @param  list<string>  $bodyVariables
     * @return list<string|null>
     */
    protected function mappingsFor(MetaWhatsAppTemplate $template, array $paramMappings, array $bodyVariables, int $paramCount): array
    {
        if ($paramCount < 1) {
            return [];
        }

        $provided = array_values($paramMappings);
        $complete = count($provided) === $paramCount
            && ! in_array(null, $provided, true)
            && ! in_array('', $provided, true);

        if ($complete) {
            return $provided;
        }

        $inferred = WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount, (string) $template->name);
        $sources = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $stored = $provided[$i] ?? null;
            $sources[] = filled($stored) ? (string) $stored : ($inferred[$i] ?? null);
        }

        return $sources;
    }
}

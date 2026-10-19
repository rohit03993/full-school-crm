<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\LoginOtpWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;
use App\Support\WhatsAppTemplateParamMappingInferrer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
        $examples = null;

        if (is_array($data['body_examples'] ?? null) && $data['body_examples'] !== []) {
            $examples = array_values(array_map(
                static fn (mixed $value): string => trim((string) $value),
                $data['body_examples'],
            ));
            $examples = array_values(array_filter($examples, static fn (string $value): bool => $value !== ''));
        }

        $headerFormat = strtoupper(trim((string) ($data['header_format'] ?? '')));
        $headerText = filled($data['header_text'] ?? null) ? (string) $data['header_text'] : null;

        if (MetaWhatsAppTemplateBuilder::isAuthenticationOtp((string) ($data['name'] ?? ''), (string) ($data['category'] ?? ''))) {
            $headerFormat = 'NONE';
            $headerText = null;
        } elseif ($headerFormat === '' || $headerFormat === 'NONE') {
            $headerFormat = filled($headerText) ? 'TEXT' : 'NONE';
        }

        $sampleHandle = in_array($headerFormat, ['IMAGE', 'VIDEO'], true)
            ? $this->sampleHandleFromUpload($data, $headerFormat)
            : null;

        $payload = MetaWhatsAppTemplateBuilder::buildCreatePayload(
            (string) ($data['name'] ?? ''),
            (string) ($data['language'] ?? 'en'),
            (string) ($data['category'] ?? 'UTILITY'),
            (string) ($data['body_text'] ?? ''),
            $headerFormat === 'TEXT' ? $headerText : null,
            filled($data['footer_text'] ?? null) ? (string) $data['footer_text'] : null,
            filled($data['body_examples_csv'] ?? null) ? (string) $data['body_examples_csv'] : null,
            (bool) ($data['allow_category_change'] ?? true),
            $examples,
            $headerFormat,
            $sampleHandle,
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

        if (($payload['category'] ?? '') === 'AUTHENTICATION') {
            $paramCount = max($paramCount, 1);
            $bodyVariables = $bodyVariables !== [] ? $bodyVariables : ['1'];
            if (blank($parsed['body'])) {
                $parsed['body'] = LoginOtpWhatsAppTemplate::BODY;
            }
        }
        $providedMappings = $data['param_mappings'] ?? null;
        $providedMappings = is_array($providedMappings) ? array_values($providedMappings) : [];
        $providedMappings = array_values(array_filter(
            $providedMappings,
            static fn (mixed $source): bool => filled($source),
        ));

        $inferredMappings = count($providedMappings) === $paramCount && $paramCount > 0
            ? $providedMappings
            : WhatsAppTemplateParamMappingInferrer::infer($bodyVariables, $paramCount, $name);

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
                    'header_format' => $headerFormat,
                ],
                'is_active' => $status === 'APPROVED',
                'synced_at' => now(),
            ],
        );

        if ($template->wasRecentlyCreated && Auth::id()) {
            $template->forceFill([
                'created_by_user_id' => Auth::id(),
            ])->save();
        }

        if ($status === 'APPROVED') {
            $this->mirrorToWhatsAppTemplate($template);
        }

        return $template;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function sampleHandleFromUpload(array $data, string $headerFormat): string
    {
        $stored = $data['header_sample'] ?? null;

        if (is_array($stored)) {
            $stored = $stored[0] ?? null;
        }

        $relative = ltrim(trim((string) $stored), '/');
        $kind = $headerFormat === 'VIDEO' ? 'MP4 video' : 'JPG or PNG image';

        if ($relative === '' || ! Storage::disk('local')->exists($relative)) {
            throw new InvalidArgumentException('Choose a sample '.$kind.'. Meta reviews this file before the template can be approved.');
        }

        $absolute = Storage::disk('local')->path($relative);
        $extension = strtolower(pathinfo($absolute, PATHINFO_EXTENSION));
        $mime = match (true) {
            $headerFormat === 'VIDEO' && $extension === 'mp4' => 'video/mp4',
            $headerFormat === 'IMAGE' && in_array($extension, ['jpg', 'jpeg'], true) => 'image/jpeg',
            $headerFormat === 'IMAGE' && $extension === 'png' => 'image/png',
            default => '',
        };

        if ($mime === '') {
            Storage::disk('local')->delete($relative);

            throw new InvalidArgumentException('The sample must be a '.$kind.'.');
        }

        $bytes = filesize($absolute);
        $maxBytes = $headerFormat === 'VIDEO' ? 16 * 1024 * 1024 : 5 * 1024 * 1024;

        if ($bytes === false || $bytes > $maxBytes) {
            Storage::disk('local')->delete($relative);

            throw new InvalidArgumentException(
                $headerFormat === 'VIDEO'
                    ? 'The sample video must be 16 MB or smaller.'
                    : 'The sample image must be 5 MB or smaller.'
            );
        }

        try {
            $result = $this->meta->uploadTemplateSample($absolute, $mime, basename($absolute));
        } finally {
            Storage::disk('local')->delete($relative);
        }

        if (($result['status'] ?? '') !== 'success' || blank($result['handle'] ?? null)) {
            throw new InvalidArgumentException((string) ($result['error'] ?? 'Meta could not accept the sample file.'));
        }

        return (string) $result['handle'];
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

<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsAppTemplateParamResolver;

class StudentWhatsAppTemplateComposer
{
    public function __construct(
        protected WhatsAppTemplateParamResolver $resolver,
    ) {}

    /**
     * @return array{
     *     param_count: int,
     *     fields: list<array{index: int, label: string, hint: string, placeholder: string}>,
     *     defaults: array<int, string>,
     *     preview_body: ?string,
     *     template_body: ?string,
     *     template_name: string
     * }
     */
    public function compose(WhatsAppTemplate $template, Student $student, ?User $sender): array
    {
        $template->ensureParamMappings();

        $metaTemplate = MetaWhatsAppTemplate::query()
            ->where('name', $template->name)
            ->where('is_active', true)
            ->orderByDesc('synced_at')
            ->first();

        $sources = $template->resolvedParamMappings();
        $paramCount = max(
            $metaTemplate ? (int) $metaTemplate->param_count : 0,
            (int) $template->param_count,
            count($sources),
        );
        $body = $metaTemplate?->body ?? $template->body;
        $bodyVariables = data_get($metaTemplate?->provider_meta ?? $template->provider_meta, 'body_variables', []);
        $bodyVariables = is_array($bodyVariables) ? array_values($bodyVariables) : [];
        $sourceOptions = WhatsAppTemplateParamResolver::sourceOptions();

        $fields = [];
        $defaults = [];

        for ($i = 0; $i < $paramCount; $i++) {
            $source = $sources[$i] ?? null;
            $value = filled($source)
                ? $this->resolver->resolve($source, $student, $sender)
                : '';

            $fields[] = [
                'index' => $i,
                'label' => $this->fieldLabel($bodyVariables[$i] ?? null, $i),
                'hint' => $this->fieldHint($source, $bodyVariables[$i] ?? null, $sourceOptions, $i),
                'placeholder' => $this->fieldPlaceholder($bodyVariables[$i] ?? null, $i, $source, $student),
                'required' => true,
            ];
            $defaults[$i] = $value;
        }

        $defaultValues = array_values($defaults);

        return [
            'param_count' => $paramCount,
            'fields' => $fields,
            'defaults' => $defaults,
            'preview_body' => $this->resolver->buildPreview($body, $defaultValues),
            'template_body' => $body,
            'template_name' => $template->name,
        ];
    }

    /**
     * @param  array<int, string>  $params
     */
    public function preview(WhatsAppTemplate $template, array $params): ?string
    {
        $metaTemplate = MetaWhatsAppTemplate::query()
            ->where('name', $template->name)
            ->where('is_active', true)
            ->orderByDesc('synced_at')
            ->first();

        $body = $metaTemplate?->body ?? $template->body;
        $count = max(
            $metaTemplate ? (int) $metaTemplate->param_count : 0,
            (int) $template->param_count,
            count($params),
        );
        $values = [];

        for ($i = 0; $i < $count; $i++) {
            $values[] = (string) ($params[$i] ?? '');
        }

        return $this->resolver->buildPreview($body, $values);
    }

    protected function fieldLabel(?string $bodyVariable, int $index): string
    {
        if (filled($bodyVariable)) {
            $clean = trim(preg_replace('/^\{\{?\d+\}?\}\s*/', '', trim($bodyVariable)) ?? trim($bodyVariable));

            if ($clean !== '') {
                return ucwords(str_replace(['_', '-'], ' ', $clean));
            }
        }

        return 'Parameter '.($index + 1);
    }

    /**
     * @param  array<string, string>  $sourceOptions
     */
    protected function fieldHint(?string $source, ?string $bodyVariable, array $sourceOptions, int $index): string
    {
        if (filled($source) && isset($sourceOptions[$source])) {
            return 'Usually filled from '.$sourceOptions[$source].'. Change if needed.';
        }

        if (filled($bodyVariable)) {
            return 'Required by Meta — matches «'.$bodyVariable.'» in the approved template.';
        }

        return 'Required — enter the value Meta expects for position '.($index + 1).'.';
    }

    protected function fieldPlaceholder(?string $bodyVariable, int $index, ?string $source, Student $student): string
    {
        if (filled($source) && $source === 'student.name' && filled($student->name)) {
            return (string) $student->name;
        }

        if (filled($bodyVariable)) {
            $clean = trim(preg_replace('/^\{\{?\d+\}?\}\s*/', '', trim($bodyVariable)) ?? trim($bodyVariable));

            if ($clean !== '') {
                return 'e.g. '.$clean;
            }
        }

        return 'Value for parameter '.($index + 1);
    }
}

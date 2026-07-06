<?php

namespace App\Support;

use App\Models\Student;
use App\Models\User;
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

        $sources = $template->resolvedParamMappings();
        $paramCount = max((int) $template->param_count, count($sources));
        $bodyVariables = data_get($template->provider_meta, 'body_variables', []);
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
                'hint' => $this->fieldHint($source, $bodyVariables[$i] ?? null, $sourceOptions),
                'placeholder' => $this->fieldLabel($bodyVariables[$i] ?? null, $i),
            ];
            $defaults[$i] = $value;
        }

        $defaultValues = array_values($defaults);

        return [
            'param_count' => $paramCount,
            'fields' => $fields,
            'defaults' => $defaults,
            'preview_body' => $this->resolver->buildPreview($template->body, $defaultValues),
            'template_body' => $template->body,
            'template_name' => $template->name,
        ];
    }

    /**
     * @param  array<int, string>  $params
     */
    public function preview(WhatsAppTemplate $template, array $params): ?string
    {
        $count = max((int) $template->param_count, count($params));
        $values = [];

        for ($i = 0; $i < $count; $i++) {
            $values[] = (string) ($params[$i] ?? '');
        }

        return $this->resolver->buildPreview($template->body, $values);
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
    protected function fieldHint(?string $source, ?string $bodyVariable, array $sourceOptions): string
    {
        if (filled($source) && isset($sourceOptions[$source])) {
            return 'Pre-filled from '.$sourceOptions[$source].'. You can edit before sending.';
        }

        if (filled($bodyVariable)) {
            return 'Enter the value for «'.$bodyVariable.'» as approved in Meta.';
        }

        return 'This value is sent to the parent in the order Meta expects.';
    }
}

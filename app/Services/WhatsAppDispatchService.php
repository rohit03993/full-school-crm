<?php

namespace App\Services;

use App\Enums\WhatsAppProvider;
use App\Models\MetaWhatsAppTemplate;
use App\Support\CrmNavigation;
use App\Support\WhatsAppTemplateParams;

class WhatsAppDispatchService
{
    public function __construct(
        protected WhatsAppProviderResolver $resolver,
        protected MetaWhatsAppService $meta,
    ) {}

    public function activeProvider(): ?WhatsAppProvider
    {
        return $this->resolver->activeProvider();
    }

    public function isConfigured(): bool
    {
        return $this->resolver->isConfigured();
    }

    public function configurationError(): string
    {
        return $this->resolver->configurationError();
    }

    public function activeProviderLabel(): string
    {
        return $this->resolver->activeProviderLabel();
    }

    /**
     * @param  list<string>  $templateParams
     * @return array{status: string, response?: mixed, error?: string, message_id?: string, provider?: string}
     */
    public function send(
        string $phone,
        array $templateParams,
        ?string $templateName,
        ?string $userName = null,
        int $expectedParamCount = 0,
        ?string $languageCode = null,
        array $logContext = [],
    ): array {
        if (! $this->isConfigured()) {
            return [
                'status' => 'failed',
                'error' => $this->configurationError(),
                'provider' => null,
            ];
        }

        $templateParams = WhatsAppTemplateParams::normalize($templateParams, $expectedParamCount);

        if (blank($templateName)) {
            return [
                'status' => 'failed',
                'error' => 'WhatsApp template name is required.',
                'provider' => WhatsAppProvider::Meta->value,
            ];
        }

        return $this->sendViaMeta($phone, $templateName, $templateParams, $languageCode, $expectedParamCount, $logContext);
    }

    /**
     * @param  list<string>  $templateParams
     * @return array{status: string, response?: mixed, error?: string, message_id?: string, provider: string}
     */
    protected function sendViaMeta(
        string $phone,
        string $templateName,
        array $templateParams,
        ?string $languageCode,
        int $expectedParamCount,
        array $logContext = [],
    ): array {
        $metaTemplate = $this->resolveMetaTemplate($templateName, $languageCode);

        if ($metaTemplate === null) {
            return [
                'status' => 'failed',
                'error' => 'Template "'.$templateName.'" is not synced. Open '.CrmNavigation::whatsAppMenu('Connection & Setup').' and click Sync templates.',
                'provider' => WhatsAppProvider::Meta->value,
            ];
        }

        $paramCount = $expectedParamCount > 0
            ? $expectedParamCount
            : (int) $metaTemplate->param_count;

        $result = $this->meta->sendTemplate(
            $phone,
            $templateName,
            $templateParams,
            $metaTemplate->language,
            $paramCount,
            $logContext,
        );

        $result['provider'] = WhatsAppProvider::Meta->value;

        return $result;
    }

    protected function resolveMetaTemplate(string $templateName, ?string $languageCode = null): ?MetaWhatsAppTemplate
    {
        $query = MetaWhatsAppTemplate::query()
            ->where('name', $templateName)
            ->where('is_active', true);

        if (filled($languageCode)) {
            $template = (clone $query)->where('language', $languageCode)->first();

            if ($template) {
                return $template;
            }
        }

        $defaultLanguage = $this->meta->defaultLanguage();

        $template = (clone $query)->where('language', $defaultLanguage)->first();

        if ($template) {
            return $template;
        }

        return $query->orderBy('language')->first();
    }
}

<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\HomeworkNotDoneWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;

class HomeworkNotDoneTemplateUpdateService
{
    public function __construct(
        protected MetaWhatsAppService $meta,
        protected MetaWhatsAppTemplateSubmitService $submit,
    ) {}

    /**
     * @return array{status: string, message: string}
     */
    public function sendUpdatedWording(): array
    {
        $fetched = $this->meta->fetchTemplates();

        if ($fetched['status'] !== 'success') {
            return [
                'status' => 'failed',
                'message' => (string) ($fetched['error'] ?? 'Could not read templates from WhatsApp.'),
            ];
        }

        $existing = collect($fetched['items'] ?? [])
            ->filter(fn (mixed $item): bool => is_array($item))
            ->first(fn (array $item): bool => MetaWhatsAppTemplateBuilder::normalizeName((string) ($item['name'] ?? '')) === HomeworkNotDoneWhatsAppTemplate::NAME);

        if (! is_array($existing)) {
            return $this->createFirstTemplate();
        }

        $currentBody = MetaWhatsAppTemplateParser::parse(
            is_array($existing['components'] ?? null) ? $existing['components'] : [],
        )['body'];
        $status = strtoupper((string) ($existing['status'] ?? ''));

        if (trim((string) $currentBody) === trim(HomeworkNotDoneWhatsAppTemplate::BODY)) {
            if ($status === 'APPROVED') {
                return [
                    'status' => 'success',
                    'message' => 'homework_not_done already has the spaced wording and WhatsApp has approved it. Click Sync from Meta if the list still shows the old sentence.',
                ];
            }

            return [
                'status' => 'success',
                'message' => 'The spaced wording is already with WhatsApp. Status is '.$status.'. Not Done messages wait until that status is APPROVED, then click Sync from Meta.',
            ];
        }

        $templateId = trim((string) ($existing['id'] ?? ''));
        $language = trim((string) ($existing['language'] ?? 'en'));
        $payload = $this->payload($language);
        $result = $this->meta->updateMessageTemplate($templateId, $payload['components']);

        if ($result['status'] !== 'success') {
            return [
                'status' => 'failed',
                'message' => (string) ($result['error'] ?? 'WhatsApp rejected the wording update.'),
            ];
        }

        $response = is_array($result['data'] ?? null) ? $result['data'] : [];
        $newStatus = strtoupper((string) ($response['status'] ?? 'PENDING'));

        if ($newStatus === '' || $newStatus === 'SUCCESS') {
            $newStatus = 'PENDING';
        }

        $this->storeLocal($language, $newStatus, $templateId, $payload['components']);

        return [
            'status' => 'success',
            'message' => 'Spaced homework_not_done wording was sent to WhatsApp. The name is unchanged. Status is '.$newStatus.'. Not Done messages pause until WhatsApp shows APPROVED. Then open Templates and click Sync from Meta, and check Automations still has this template selected.',
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    protected function createFirstTemplate(): array
    {
        try {
            $template = $this->submit->submit([
                'name' => HomeworkNotDoneWhatsAppTemplate::NAME,
                'language' => 'en',
                'category' => HomeworkNotDoneWhatsAppTemplate::CATEGORY,
                'body_text' => HomeworkNotDoneWhatsAppTemplate::BODY,
                'body_examples' => HomeworkNotDoneWhatsAppTemplate::exampleValues(),
                'param_mappings' => HomeworkNotDoneWhatsAppTemplate::mappingSources(),
            ]);
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'homework_not_done was created on WhatsApp. Status is '.$template->status.'. After APPROVED, click Sync from Meta and select it under Automations.',
        ];
    }

    /**
     * @return array{name: string, language: string, category: string, components: list<array<string, mixed>>}
     */
    protected function payload(string $language): array
    {
        return MetaWhatsAppTemplateBuilder::buildCreatePayload(
            HomeworkNotDoneWhatsAppTemplate::NAME,
            $language !== '' ? $language : 'en',
            HomeworkNotDoneWhatsAppTemplate::CATEGORY,
            HomeworkNotDoneWhatsAppTemplate::BODY,
            bodyExamples: HomeworkNotDoneWhatsAppTemplate::exampleValues(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    protected function storeLocal(string $language, string $status, string $templateId, array $components): void
    {
        $parsed = MetaWhatsAppTemplateParser::parse($components);
        $approved = $status === 'APPROVED';
        $mappings = HomeworkNotDoneWhatsAppTemplate::mappingSources();

        MetaWhatsAppTemplate::query()->updateOrCreate(
            ['name' => HomeworkNotDoneWhatsAppTemplate::NAME, 'language' => $language !== '' ? $language : 'en'],
            [
                'status' => $status,
                'param_count' => (int) $parsed['param_count'],
                'param_mappings' => $mappings,
                'body' => $parsed['body'],
                'components' => $components,
                'provider_meta' => [
                    'body_variables' => $parsed['body_variables'],
                    'category' => HomeworkNotDoneWhatsAppTemplate::CATEGORY,
                    'id' => $templateId,
                    'source' => 'crm_update',
                ],
                'is_active' => $approved,
                'synced_at' => now(),
            ],
        );

        WhatsAppTemplate::query()->updateOrCreate(
            ['name' => HomeworkNotDoneWhatsAppTemplate::NAME],
            [
                'description' => 'Synced from Meta ('.$language.')',
                'param_count' => (int) $parsed['param_count'],
                'body' => $parsed['body'],
                'param_mappings' => $mappings,
                'is_active' => $approved,
                'synced_at' => now(),
            ],
        );
    }
}

<?php

namespace App\Services;

use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppTemplate;
use App\Support\CombinedHomeworkWhatsAppTemplate;
use App\Support\MetaWhatsAppTemplateBuilder;
use App\Support\MetaWhatsAppTemplateParser;

class HomeworkCombinedTemplateUpdateService
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
            ->first(fn (array $item): bool => MetaWhatsAppTemplateBuilder::normalizeName((string) ($item['name'] ?? '')) === CombinedHomeworkWhatsAppTemplate::NAME);

        if (! is_array($existing)) {
            return $this->createFirstTemplate();
        }

        $currentBody = MetaWhatsAppTemplateParser::parse(
            is_array($existing['components'] ?? null) ? $existing['components'] : [],
        )['body'];
        $status = strtoupper((string) ($existing['status'] ?? ''));

        if (trim((string) $currentBody) === trim(CombinedHomeworkWhatsAppTemplate::BODY)) {
            if ($status === 'APPROVED') {
                return [
                    'status' => 'success',
                    'message' => 'homework_combined already has one subject per line and WhatsApp has approved it. Click Sync from Meta if the list still shows the old paragraph.',
                ];
            }

            return [
                'status' => 'success',
                'message' => 'The one-subject-per-line wording is already with WhatsApp. Status is '.$status.'. Send to parents keeps the old paragraph until that status is APPROVED, then click Sync from Meta.',
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
            'message' => 'The homework_combined template was updated on WhatsApp. The name is the same. Status is '.$newStatus.'. Parents still get the old paragraph until WhatsApp shows APPROVED. Then open Templates and click Sync from Meta.',
        ];
    }

    /**
     * @return array{status: string, message: string}
     */
    protected function createFirstTemplate(): array
    {
        try {
            $template = $this->submit->submit([
                'name' => CombinedHomeworkWhatsAppTemplate::NAME,
                'language' => 'en',
                'category' => CombinedHomeworkWhatsAppTemplate::CATEGORY,
                'body_text' => CombinedHomeworkWhatsAppTemplate::BODY,
                'body_examples' => CombinedHomeworkWhatsAppTemplate::exampleValues(),
            ]);
        } catch (\Throwable $exception) {
            return [
                'status' => 'failed',
                'message' => $exception->getMessage(),
            ];
        }

        return [
            'status' => 'success',
            'message' => 'homework_combined was created on WhatsApp. Status is '.$template->status.'. After APPROVED, click Sync from Meta and select it under Automations.',
        ];
    }

    /**
     * @return array{name: string, language: string, category: string, components: list<array<string, mixed>>}
     */
    protected function payload(string $language): array
    {
        return MetaWhatsAppTemplateBuilder::buildCreatePayload(
            CombinedHomeworkWhatsAppTemplate::NAME,
            $language !== '' ? $language : 'en',
            CombinedHomeworkWhatsAppTemplate::CATEGORY,
            CombinedHomeworkWhatsAppTemplate::BODY,
            bodyExamples: CombinedHomeworkWhatsAppTemplate::exampleValues(),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $components
     */
    protected function storeLocal(string $language, string $status, string $templateId, array $components): void
    {
        $parsed = MetaWhatsAppTemplateParser::parse($components);
        $approved = $status === 'APPROVED';

        MetaWhatsAppTemplate::query()->updateOrCreate(
            ['name' => CombinedHomeworkWhatsAppTemplate::NAME, 'language' => $language !== '' ? $language : 'en'],
            [
                'status' => $status,
                'param_count' => (int) $parsed['param_count'],
                'body' => $parsed['body'],
                'components' => $components,
                'provider_meta' => [
                    'body_variables' => $parsed['body_variables'],
                    'category' => CombinedHomeworkWhatsAppTemplate::CATEGORY,
                    'id' => $templateId,
                    'source' => 'crm_update',
                ],
                'is_active' => $approved,
                'synced_at' => now(),
            ],
        );

        WhatsAppTemplate::query()->updateOrCreate(
            ['name' => CombinedHomeworkWhatsAppTemplate::NAME],
            [
                'description' => 'Synced from Meta ('.$language.')',
                'param_count' => (int) $parsed['param_count'],
                'body' => $parsed['body'],
                'provider_meta' => [
                    'category' => CombinedHomeworkWhatsAppTemplate::CATEGORY,
                    'id' => $templateId,
                    'source' => 'crm_update',
                ],
                'is_active' => $approved,
                'synced_at' => now(),
            ],
        );
    }
}

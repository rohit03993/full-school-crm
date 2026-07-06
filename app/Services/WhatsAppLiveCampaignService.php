<?php

namespace App\Services;

use App\Enums\WhatsAppLiveCampaignStatus;
use App\Models\MetaWhatsAppTemplate;
use App\Models\WhatsAppLiveCampaign;
use App\Support\CrmNavigation;

class WhatsAppLiveCampaignService
{
    public function __construct(
        protected WhatsAppDispatchService $dispatch,
        protected WhatsAppProviderResolver $resolver,
    ) {}

    public function resolveByName(string $campaignName): ?WhatsAppLiveCampaign
    {
        $campaignName = trim($campaignName);

        if ($campaignName === '') {
            return null;
        }

        $live = WhatsAppLiveCampaign::query()
            ->with('metaTemplate')
            ->where('status', WhatsAppLiveCampaignStatus::Live)
            ->where('name', $campaignName)
            ->first();

        if ($live) {
            return $live;
        }

        return WhatsAppLiveCampaign::query()
            ->with('metaTemplate')
            ->where('status', WhatsAppLiveCampaignStatus::Live)
            ->whereHas('metaTemplate', fn ($query) => $query
                ->where('name', $campaignName)
                ->where('status', 'APPROVED')
                ->where('is_active', true))
            ->orderBy('name')
            ->first();
    }

    /**
     * @param  list<mixed>  $templateParams
     * @return array{status: string, response?: mixed, error?: string, message_id?: string, campaign_id?: int, provider?: string}
     */
    public function triggerByName(
        string $campaignName,
        string $phone,
        ?string $userName = null,
        array $templateParams = [],
    ): array {
        if (! $this->resolver->isConfigured()) {
            return [
                'status' => 'failed',
                'error' => $this->resolver->configurationError(),
            ];
        }

        $campaign = $this->resolveByName($campaignName);

        if ($campaign === null) {
            return [
                'status' => 'failed',
                'error' => "No live API campaign found for campaignName '{$campaignName}'. "
                    .'Create one under '.CrmNavigation::whatsAppMenu('Live campaigns').' and set it Live.',
            ];
        }

        return $this->trigger($campaign, $phone, $userName, $templateParams);
    }

    /**
     * @param  list<mixed>  $templateParams
     * @return array{status: string, response?: mixed, error?: string, message_id?: string, campaign_id?: int, provider?: string}
     */
    public function trigger(
        WhatsAppLiveCampaign $campaign,
        string $phone,
        ?string $userName = null,
        array $templateParams = [],
    ): array {
        $metaTemplate = $campaign->metaTemplate;

        if ($metaTemplate === null || strtoupper($metaTemplate->status) !== 'APPROVED' || ! $metaTemplate->is_active) {
            return [
                'status' => 'failed',
                'error' => 'Linked template is not approved for sending.',
                'campaign_id' => $campaign->id,
            ];
        }

        $params = $this->mergeTemplateParams($campaign, $templateParams, $userName);
        $paramCount = (int) $metaTemplate->param_count;

        $result = $this->dispatch->send(
            $phone,
            $params,
            $metaTemplate->name,
            $userName,
            $paramCount,
            $metaTemplate->language,
        );

        $result['campaign_id'] = $campaign->id;

        return $result;
    }

    public function goLive(WhatsAppLiveCampaign $campaign): WhatsAppLiveCampaign
    {
        $template = $campaign->metaTemplate;

        if ($template === null || strtoupper($template->status) !== 'APPROVED') {
            throw new \InvalidArgumentException('Cannot go live — linked template must be APPROVED in Meta.');
        }

        $campaign->update([
            'status' => WhatsAppLiveCampaignStatus::Live,
            'went_live_at' => $campaign->went_live_at ?? now(),
        ]);

        return $campaign->fresh() ?? $campaign;
    }

    public function pause(WhatsAppLiveCampaign $campaign): WhatsAppLiveCampaign
    {
        $campaign->update([
            'status' => WhatsAppLiveCampaignStatus::Draft,
        ]);

        return $campaign->fresh() ?? $campaign;
    }

    public function whatsAppTemplateIdForCampaign(?int $liveCampaignId): ?int
    {
        if (! filled($liveCampaignId)) {
            return null;
        }

        $campaign = WhatsAppLiveCampaign::query()
            ->with('metaTemplate')
            ->whereKey($liveCampaignId)
            ->where('status', WhatsAppLiveCampaignStatus::Live)
            ->first();

        if ($campaign?->metaTemplate === null) {
            return null;
        }

        return \App\Models\WhatsAppTemplate::query()
            ->where('name', $campaign->metaTemplate->name)
            ->where('is_active', true)
            ->value('id');
    }

    /**
     * @param  list<mixed>  $templateParams
     * @return list<string>
     */
    protected function mergeTemplateParams(
        WhatsAppLiveCampaign $campaign,
        array $templateParams,
        ?string $userName,
    ): array {
        $params = collect($templateParams)
            ->map(fn (mixed $value): string => trim((string) $value))
            ->values()
            ->all();

        $defaults = $campaign->default_variables ?? [];

        if (is_array($defaults)) {
            foreach ($defaults as $index => $value) {
                if (! is_numeric($index)) {
                    continue;
                }

                $i = (int) $index;

                if (! isset($params[$i]) || $params[$i] === '') {
                    $params[$i] = trim((string) $value);
                }
            }
        }

        if (filled($userName) && ($params[0] ?? '') === '') {
            $params[0] = trim((string) $userName);
        }

        return array_values($params);
    }
}

<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppPricingCategory;
use App\Models\MetaWhatsAppMessage;
use App\Models\WhatsAppCampaign;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class WhatsAppAnalyticsService
{
    public function __construct(
        protected MetaWhatsAppPricingAnalyticsService $metaPricing,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function summary(?Carbon $from = null, ?Carbon $to = null): array
    {
        [$from, $to] = $this->normalizeRange($from, $to);

        $meta = $this->metaPricing->fetchForRange($from, $to);
        $local = $this->localOutboundSummary($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'meta' => $meta,
            'local' => $local,
            'gap' => $this->coverageGap($meta, $local),
            'campaigns' => $this->campaignBreakdown($from, $to),
            'by_source' => $this->breakdownBySource($from, $to),
            'by_category' => $this->breakdownByCategory($from, $to),
        ];
    }

    /**
     * Compare Meta official delivery volume with CRM outbound log coverage.
     *
     * @param  array<string, mixed>  $meta
     * @param  array{total_messages: int, total_cost_inr: float}  $local
     * @return array{
     *     meta_available: bool,
     *     meta_volume: int,
     *     crm_volume: int,
     *     missing_from_crm: int,
     *     coverage_percent: float,
     *     meta_cost: float,
     *     crm_estimated_cost: float,
     *     currency: string
     * }
     */
    public function coverageGap(array $meta, array $local): array
    {
        $metaOk = ($meta['status'] ?? '') === 'success';
        $metaVolume = $metaOk ? (int) ($meta['total_volume'] ?? 0) : 0;
        $crmVolume = (int) ($local['total_messages'] ?? 0);
        $missing = max(0, $metaVolume - $crmVolume);
        $coverage = $metaVolume > 0
            ? round(($crmVolume / $metaVolume) * 100, 1)
            : ($crmVolume > 0 ? 100.0 : 0.0);

        return [
            'meta_available' => $metaOk,
            'meta_volume' => $metaVolume,
            'crm_volume' => $crmVolume,
            'missing_from_crm' => $missing,
            'coverage_percent' => $coverage,
            'meta_cost' => $metaOk ? round((float) ($meta['total_cost'] ?? 0), 4) : 0.0,
            'crm_estimated_cost' => round((float) ($local['total_cost_inr'] ?? 0), 4),
            'currency' => $metaOk ? (string) ($meta['currency'] ?? 'INR') : 'INR',
        ];
    }

    /**
     * @return array{from: string, to: string, total_messages: int, total_cost_inr: float, by_category: array<string, array{count: int, cost_inr: float}>}
     */
    public function localOutboundSummary(Carbon $from, Carbon $to): array
    {
        $messages = $this->outboundQuery($from, $to)->get();

        $byCategory = [];
        $totalCost = 0.0;

        foreach ($messages as $message) {
            $category = (string) ($message->conversation_category ?: WhatsAppPricingCategory::Unknown->value);

            if (! isset($byCategory[$category])) {
                $byCategory[$category] = ['count' => 0, 'cost_inr' => 0.0];
            }

            $cost = (float) ($message->estimated_cost_inr ?? 0);
            $byCategory[$category]['count']++;
            $byCategory[$category]['cost_inr'] += $cost;
            $totalCost += $cost;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_messages' => $messages->count(),
            'total_cost_inr' => round($totalCost, 4),
            'by_category' => $byCategory,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function campaignBreakdown(Carbon $from, Carbon $to): array
    {
        return WhatsAppCampaign::query()
            ->with(['template:id,name', 'batch:id,name'])
            ->whereNotNull('shot_at')
            ->whereBetween('shot_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderByDesc('shot_at')
            ->limit(50)
            ->get()
            ->map(function (WhatsAppCampaign $campaign): array {
                return [
                    'id' => $campaign->id,
                    'name' => $campaign->name,
                    'template' => $campaign->template?->name,
                    'batch' => $campaign->batch?->name,
                    'shot_at' => $campaign->shot_at?->format('d M Y, g:i A'),
                    'total_recipients' => (int) $campaign->total_recipients,
                    'sent_count' => (int) $campaign->sent_count,
                    'failed_count' => (int) $campaign->failed_count,
                    'estimated_total_cost_inr' => round((float) $campaign->estimated_total_cost_inr, 4),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{count: int, cost_inr: float}>
     */
    public function breakdownBySource(Carbon $from, Carbon $to): array
    {
        $rows = $this->outboundQuery($from, $to)
            ->selectRaw('message_source, COUNT(*) as message_count, COALESCE(SUM(estimated_cost_inr), 0) as total_cost')
            ->groupBy('message_source')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $source = (string) ($row->message_source ?: WhatsAppMessageSource::Automation->value);
            $breakdown[$source] = [
                'count' => (int) $row->message_count,
                'cost_inr' => round((float) $row->total_cost, 4),
            ];
        }

        return $breakdown;
    }

    /**
     * @return array<string, array{count: int, cost_inr: float}>
     */
    public function breakdownByCategory(Carbon $from, Carbon $to): array
    {
        $rows = $this->outboundQuery($from, $to)
            ->selectRaw('conversation_category, COUNT(*) as message_count, COALESCE(SUM(estimated_cost_inr), 0) as total_cost')
            ->groupBy('conversation_category')
            ->get();

        $breakdown = [];

        foreach ($rows as $row) {
            $category = (string) ($row->conversation_category ?: WhatsAppPricingCategory::Unknown->value);
            $breakdown[$category] = [
                'count' => (int) $row->message_count,
                'cost_inr' => round((float) $row->total_cost, 4),
            ];
        }

        return $breakdown;
    }

    public function refreshCampaignCostTotals(WhatsAppCampaign $campaign): void
    {
        $total = $campaign->recipients()
            ->whereNotNull('estimated_cost_inr')
            ->sum('estimated_cost_inr');

        $campaign->update(['estimated_total_cost_inr' => round((float) $total, 4)]);
    }

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    protected function normalizeRange(?Carbon $from, ?Carbon $to): array
    {
        $to = ($to ?? now())->copy()->endOfDay();
        $from = ($from ?? now()->subYears(10))->copy()->startOfDay();

        if ($from->greaterThan($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    protected function outboundQuery(Carbon $from, Carbon $to): Builder
    {
        return MetaWhatsAppMessage::query()
            ->where('direction', MetaWhatsAppMessageDirection::Outbound->value)
            ->whereBetween('status_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
    }
}

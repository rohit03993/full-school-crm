<?php

namespace App\Services;

use App\Enums\MetaWhatsAppMessageDirection;
use App\Enums\MetaWhatsAppMessageStatus;
use App\Enums\WhatsAppMessageSource;
use App\Enums\WhatsAppPricingCategory;
use App\Enums\WhatsAppRecipientStatus;
use App\Enums\WhatsAppSendActor;
use App\Models\MetaWhatsAppMessage;
use App\Models\User;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppCampaignRecipient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;

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

        $this->settleFailedDeliveryCosts();

        $meta = $this->metaPricing->fetchForRange($from, $to);
        $local = $this->localOutboundSummary($from, $to);

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'meta' => $meta,
            'local' => $local,
            'gap' => $this->coverageGap($meta, $local),
            'by_day' => $this->dailyBreakdown($from, $to, is_array($meta['data_points'] ?? null) ? $meta['data_points'] : []),
            'campaigns' => $this->campaignBreakdown($from, $to),
            'by_source' => $this->breakdownBySource($from, $to),
            'by_category' => $this->breakdownByCategory($from, $to),
            'by_staff' => $this->breakdownByStaff($from, $to),
        ];
    }

    /**
     * A message Meta refused after accepting is not a charge.
     * Clear its rupee and move the campaign person from Sent to Failed.
     */
    public function markUndelivered(MetaWhatsAppMessage $message, ?string $detail = null): void
    {
        if ((float) $message->estimated_cost_inr !== 0.0) {
            $message->forceFill(['estimated_cost_inr' => 0])->save();
        }

        $recipient = $this->recipientForFailedMessage($message);

        if (! $recipient) {
            return;
        }

        $recipient->status = WhatsAppRecipientStatus::Failed;
        $recipient->estimated_cost_inr = 0;

        if (blank($recipient->error_message)) {
            $recipient->error_message = filled($detail) ? $detail : 'WhatsApp did not deliver this message.';
        }

        $recipient->save();

        $campaign = $recipient->campaign;

        if ($campaign) {
            $this->recountCampaign($campaign);
        }
    }

    /**
     * Fix older failed messages that still carry a rupee from the moment Meta accepted them.
     */
    public function settleFailedDeliveryCosts(?WhatsAppCampaign $only = null): void
    {
        $failedQuery = MetaWhatsAppMessage::query()
            ->where('direction', MetaWhatsAppMessageDirection::Outbound->value)
            ->where('status', MetaWhatsAppMessageStatus::Failed->value);

        if ($only) {
            $this->scopeMessagesToCampaign($failedQuery, $only);
        }

        (clone $failedQuery)
            ->where('estimated_cost_inr', '>', 0)
            ->update(['estimated_cost_inr' => 0]);

        $rows = (clone $failedQuery)->get(['id', 'wamid', 'whatsapp_campaign_recipient_id']);

        if ($rows->isEmpty()) {
            return;
        }

        $recipientIds = $rows->pluck('whatsapp_campaign_recipient_id')->filter()->unique()->values()->all();
        $wamids = $rows->pluck('wamid')->filter()->unique()->values()->all();
        $messageIds = $rows->pluck('id')->all();

        $recipients = WhatsAppCampaignRecipient::query()
            ->where('status', '!=', WhatsAppRecipientStatus::Failed->value)
            ->when($only, fn (Builder $query) => $query->where('whatsapp_campaign_id', $only->id))
            ->where(function (Builder $query) use ($recipientIds, $wamids, $messageIds): void {
                $open = false;

                if ($recipientIds !== []) {
                    $query->whereIn('id', $recipientIds);
                    $open = true;
                }

                if ($wamids !== []) {
                    $open ? $query->orWhereIn('wamid', $wamids) : $query->whereIn('wamid', $wamids);
                    $open = true;
                }

                if ($messageIds !== []) {
                    $open ? $query->orWhereIn('meta_whatsapp_message_id', $messageIds) : $query->whereIn('meta_whatsapp_message_id', $messageIds);
                    $open = true;
                }

                if (! $open) {
                    $query->whereRaw('0 = 1');
                }
            })
            ->get();

        if ($recipients->isEmpty()) {
            return;
        }

        foreach ($recipients as $recipient) {
            $recipient->status = WhatsAppRecipientStatus::Failed;
            $recipient->estimated_cost_inr = 0;

            if (blank($recipient->error_message)) {
                $recipient->error_message = 'WhatsApp did not deliver this message.';
            }

            $recipient->save();
        }

        foreach ($recipients->pluck('whatsapp_campaign_id')->unique() as $campaignId) {
            $campaign = WhatsAppCampaign::query()->find($campaignId);

            if ($campaign) {
                $this->recountCampaign($campaign);
            }
        }
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

            $failed = (string) $message->status === MetaWhatsAppMessageStatus::Failed->value;
            $cost = $failed ? 0.0 : (float) ($message->estimated_cost_inr ?? 0);
            $byCategory[$category]['count']++;
            $byCategory[$category]['cost_inr'] += $cost;
            $totalCost += $cost;
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
            'total_messages' => $messages->count(),
            'failed_messages' => $messages->where('status', MetaWhatsAppMessageStatus::Failed->value)->count(),
            'total_cost_inr' => round($totalCost, 4),
            'by_category' => $byCategory,
        ];
    }

    /**
     * One row per date. Meta billed is the official charge. CRM estimate skips failed sends.
     *
     * @param  list<array<string, mixed>>  $metaPoints
     * @return list<array{date: string, date_label: string, meta_volume: int, meta_cost: float, crm_messages: int, crm_failed: int, crm_cost_inr: float}>
     */
    public function dailyBreakdown(Carbon $from, Carbon $to, array $metaPoints = []): array
    {
        $rows = $this->outboundQuery($from, $to)
            ->selectRaw('date(status_at) as day')
            ->selectRaw('COUNT(*) as message_count')
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_count")
            ->selectRaw("SUM(CASE WHEN status = 'failed' THEN 0 ELSE COALESCE(estimated_cost_inr, 0) END) as billable_cost")
            ->groupByRaw('date(status_at)')
            ->orderByRaw('date(status_at)')
            ->get();

        $days = [];

        foreach ($rows as $row) {
            $day = (string) $row->day;

            if ($day === '') {
                continue;
            }

            $days[$day] = $this->emptyDayRow($day);
            $days[$day]['crm_messages'] = (int) $row->message_count;
            $days[$day]['crm_failed'] = (int) $row->failed_count;
            $days[$day]['crm_cost_inr'] = round((float) $row->billable_cost, 4);
        }

        foreach ($metaPoints as $point) {
            if (! is_array($point) || ! is_numeric($point['start'] ?? null)) {
                continue;
            }

            $day = Carbon::createFromTimestamp((int) $point['start'])
                ->timezone(config('app.timezone'))
                ->toDateString();

            if (! isset($days[$day])) {
                $days[$day] = $this->emptyDayRow($day);
            }

            $days[$day]['meta_volume'] += (int) ($point['volume'] ?? 0);
            $days[$day]['meta_cost'] += (float) ($point['cost'] ?? 0);
        }

        krsort($days);

        return array_values(array_map(function (array $day): array {
            $day['meta_cost'] = round($day['meta_cost'], 4);

            return $day;
        }, $days));
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
            ->selectRaw("message_source, COUNT(*) as message_count, COALESCE(SUM(CASE WHEN status = 'failed' THEN 0 ELSE estimated_cost_inr END), 0) as total_cost")
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
            ->selectRaw("conversation_category, COUNT(*) as message_count, COALESCE(SUM(CASE WHEN status = 'failed' THEN 0 ELSE estimated_cost_inr END), 0) as total_cost")
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

    /**
     * CRM-estimated cost attributed to staff who sent inbox / campaign / test messages.
     * Automatic and system sends are excluded (no sent_by_user_id).
     *
     * @return list<array{user_id: int|null, name: string, count: int, cost_inr: float}>
     */
    public function breakdownByStaff(Carbon $from, Carbon $to): array
    {
        if (! Schema::hasColumn('meta_whatsapp_messages', 'sent_by_user_id')) {
            return [];
        }

        $rows = $this->outboundQuery($from, $to)
            ->where(function (Builder $query): void {
                $query->where('send_actor', WhatsAppSendActor::Staff->value)
                    ->orWhere(function (Builder $inner): void {
                        $inner->whereNull('send_actor')
                            ->whereNotNull('sent_by_user_id');
                    });
            })
            ->selectRaw("sent_by_user_id, COUNT(*) as message_count, COALESCE(SUM(CASE WHEN status = 'failed' THEN 0 ELSE estimated_cost_inr END), 0) as total_cost")
            ->groupBy('sent_by_user_id')
            ->orderByDesc('total_cost')
            ->get();

        if ($rows->isEmpty()) {
            return [];
        }

        $names = User::query()
            ->whereIn('id', $rows->pluck('sent_by_user_id')->filter()->all())
            ->pluck('name', 'id');

        return $rows
            ->map(function ($row) use ($names): array {
                $userId = $row->sent_by_user_id ? (int) $row->sent_by_user_id : null;

                return [
                    'user_id' => $userId,
                    'name' => $userId ? (string) ($names[$userId] ?? 'Former staff #'.$userId) : 'Unknown staff',
                    'count' => (int) $row->message_count,
                    'cost_inr' => round((float) $row->total_cost, 4),
                ];
            })
            ->values()
            ->all();
    }

    public function refreshCampaignCostTotals(WhatsAppCampaign $campaign): void
    {
        $total = $campaign->recipients()
            ->where('status', '!=', WhatsAppRecipientStatus::Failed->value)
            ->whereNotNull('estimated_cost_inr')
            ->sum('estimated_cost_inr');

        $campaign->update(['estimated_total_cost_inr' => round((float) $total, 4)]);
    }

    public function recountCampaign(WhatsAppCampaign $campaign): void
    {
        $sentCount = $campaign->recipients()
            ->where('status', WhatsAppRecipientStatus::Sent->value)
            ->count();

        $failedCount = $campaign->recipients()
            ->where('status', WhatsAppRecipientStatus::Failed->value)
            ->count();

        $campaign->update([
            'sent_count' => $sentCount,
            'failed_count' => $failedCount,
        ]);

        $this->refreshCampaignCostTotals($campaign->fresh() ?? $campaign);
    }

    protected function recipientForFailedMessage(MetaWhatsAppMessage $message): ?WhatsAppCampaignRecipient
    {
        if ($message->whatsapp_campaign_recipient_id) {
            $recipient = WhatsAppCampaignRecipient::query()->find($message->whatsapp_campaign_recipient_id);

            if ($recipient) {
                return $recipient;
            }
        }

        if (filled($message->wamid)) {
            $recipient = WhatsAppCampaignRecipient::query()->where('wamid', $message->wamid)->first();

            if ($recipient) {
                return $recipient;
            }
        }

        return WhatsAppCampaignRecipient::query()
            ->where('meta_whatsapp_message_id', $message->id)
            ->first();
    }

    protected function scopeMessagesToCampaign(Builder $query, WhatsAppCampaign $campaign): void
    {
        $recipientIds = $campaign->recipients()->pluck('id');
        $wamids = $campaign->recipients()->whereNotNull('wamid')->pluck('wamid');
        $messageIds = $campaign->recipients()->whereNotNull('meta_whatsapp_message_id')->pluck('meta_whatsapp_message_id');

        $query->where(function (Builder $inner) use ($recipientIds, $wamids, $messageIds): void {
            $open = false;

            if ($recipientIds->isNotEmpty()) {
                $inner->whereIn('whatsapp_campaign_recipient_id', $recipientIds->all());
                $open = true;
            }

            if ($wamids->isNotEmpty()) {
                $open ? $inner->orWhereIn('wamid', $wamids->all()) : $inner->whereIn('wamid', $wamids->all());
                $open = true;
            }

            if ($messageIds->isNotEmpty()) {
                $open ? $inner->orWhereIn('id', $messageIds->all()) : $inner->whereIn('id', $messageIds->all());
                $open = true;
            }

            if (! $open) {
                $inner->whereRaw('0 = 1');
            }
        });
    }

    /**
     * @return array{date: string, date_label: string, meta_volume: int, meta_cost: float, crm_messages: int, crm_failed: int, crm_cost_inr: float}
     */
    protected function emptyDayRow(string $day): array
    {
        return [
            'date' => $day,
            'date_label' => Carbon::parse($day)->format('D, d M Y'),
            'meta_volume' => 0,
            'meta_cost' => 0.0,
            'crm_messages' => 0,
            'crm_failed' => 0,
            'crm_cost_inr' => 0.0,
        ];
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

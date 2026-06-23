<?php

namespace App\Support;

use App\Enums\WhatsAppCampaignStatus;
use App\Models\WhatsAppCampaign;
use Illuminate\Support\HtmlString;

class WhatsAppCampaignViewHelper
{
    public static function isInProgress(WhatsAppCampaign $campaign): bool
    {
        return in_array($campaign->status, [
            WhatsAppCampaignStatus::Queued,
            WhatsAppCampaignStatus::Running,
        ], true);
    }

    public static function hasCampaignVariables(WhatsAppCampaign $campaign): bool
    {
        $variables = $campaign->campaign_variables ?? [];

        if ($variables === []) {
            return false;
        }

        $manual = $variables['_manual'] ?? [];

        if (is_array($manual) && collect($manual)->filter(fn (mixed $v): bool => filled($v))->isNotEmpty()) {
            return true;
        }

        return collect($variables)
            ->except('_manual')
            ->filter(fn (mixed $value): bool => filled($value) && ! is_array($value))
            ->isNotEmpty();
    }

    public static function renderStatsDashboard(WhatsAppCampaign $campaign): HtmlString
    {
        $total = (int) $campaign->total_recipients;
        $sent = (int) $campaign->sent_count;
        $failed = (int) $campaign->failed_count;
        $pending = max(0, $total - $sent - $failed);
        $successRate = $total > 0 ? (int) round(($sent / $total) * 100) : 0;

        $cards = [
            ['label' => 'Recipients', 'value' => (string) $total, 'tone' => 'text-gray-950 dark:text-white'],
            ['label' => 'Sent', 'value' => (string) $sent, 'tone' => 'text-success-600 dark:text-success-400'],
            ['label' => 'Failed', 'value' => (string) $failed, 'tone' => $failed > 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-500 dark:text-gray-400'],
            ['label' => 'Pending', 'value' => (string) $pending, 'tone' => $pending > 0 ? 'text-warning-600 dark:text-warning-400' : 'text-gray-500 dark:text-gray-400'],
        ];

        $cardHtml = collect($cards)->map(fn (array $card): string => '<div class="rounded-xl border border-gray-200 bg-white p-4 text-center shadow-sm dark:border-white/10 dark:bg-white/5">'
            .'<p class="text-3xl font-bold tabular-nums '.$card['tone'].'">'.e($card['value']).'</p>'
            .'<p class="mt-1 text-sm text-gray-500 dark:text-gray-400">'.e($card['label']).'</p>'
            .'</div>')->implode('');

        $meta = '<div class="mt-4 flex flex-wrap items-center gap-x-6 gap-y-2 text-sm text-gray-600 dark:text-gray-300">'
            .'<span><strong>'.e($campaign->template?->name ?? '—').'</strong> template</span>'
            .'<span>'.e($campaign->course?->name ?? '—').'</span>'
            .($campaign->batch ? '<span>'.e($campaign->batch->name).'</span>' : '')
            .'<span>'.$successRate.'% delivered</span>'
            .'</div>';

        return new HtmlString(
            '<div class="grid grid-cols-2 gap-3 lg:grid-cols-4">'.$cardHtml.'</div>'.$meta
        );
    }

    public static function renderSendProgress(WhatsAppCampaign $campaign): HtmlString
    {
        $total = max(1, (int) $campaign->total_recipients);
        $sent = (int) $campaign->sent_count;
        $failed = (int) $campaign->failed_count;
        $done = min($total, $sent + $failed);
        $pending = max(0, $total - $done);
        $percent = (int) round(($done / $total) * 100);

        $statusLabel = $campaign->status instanceof WhatsAppCampaignStatus
            ? $campaign->status->label()
            : ucfirst((string) $campaign->status);

        $barColor = $failed > 0 && $pending === 0
            ? 'bg-warning-500'
            : 'bg-primary-500';

        return new HtmlString(
            '<div class="space-y-3">'
            .'<div class="flex flex-wrap items-center justify-between gap-2 text-sm">'
            .'<span class="font-medium text-gray-950 dark:text-white">'.$statusLabel.' — '.$done.' of '.$total.' processed</span>'
            .'<span class="text-gray-500 dark:text-gray-400">'.$percent.'%</span>'
            .'</div>'
            .'<div class="h-2.5 w-full overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">'
            .'<div class="h-full rounded-full transition-all duration-500 '.$barColor.'" style="width: '.$percent.'%"></div>'
            .'</div>'
            .'<div class="flex flex-wrap gap-4 text-sm text-gray-600 dark:text-gray-300">'
            .'<span><strong class="text-success-600 dark:text-success-400">'.$sent.'</strong> sent</span>'
            .'<span><strong class="text-danger-600 dark:text-danger-400">'.$failed.'</strong> failed</span>'
            .'<span><strong>'.$pending.'</strong> pending</span>'
            .'</div>'
            .(self::isInProgress($campaign)
                ? '<p class="text-xs text-gray-500 dark:text-gray-400">Page refreshes every few seconds while messages are sending.</p>'
                : '')
            .'</div>'
        );
    }

    public static function renderCampaignVariables(WhatsAppCampaign $campaign): HtmlString
    {
        $variables = $campaign->campaign_variables ?? [];
        $manual = is_array($variables['_manual'] ?? null) ? $variables['_manual'] : [];
        $bodyVariables = data_get($campaign->template?->provider_meta, 'body_variables', []);
        $items = [];

        foreach ($manual as $index => $value) {
            if (blank($value)) {
                continue;
            }

            $slot = (int) $index + 1;
            $label = is_array($bodyVariables) && filled($bodyVariables[$index] ?? null)
                ? (string) $bodyVariables[$index]
                : 'Parameter '.$slot;

            $items[] = [
                'slot' => '{{'.$slot.'}}',
                'label' => $label,
                'value' => (string) $value,
            ];
        }

        foreach ([
            'topic' => 'Topic',
            'subject' => 'Subject',
            'date' => 'Date',
            'time' => 'Check-in time',
            'attendance_date' => 'Attendance date',
        ] as $key => $label) {
            if (filled($variables[$key] ?? null) && ! is_array($variables[$key])) {
                $items[] = [
                    'slot' => null,
                    'label' => $label,
                    'value' => (string) $variables[$key],
                ];
            }
        }

        if ($items === []) {
            return new HtmlString(
                '<p class="text-sm text-gray-500 dark:text-gray-400">No parameters were set for this campaign.</p>'
            );
        }

        $rows = collect($items)->map(function (array $item): string {
            $slot = filled($item['slot'])
                ? '<span class="mr-2 inline-flex rounded bg-gray-100 px-2 py-0.5 font-mono text-xs text-gray-600 dark:bg-white/10 dark:text-gray-300">'
                    .e($item['slot']).'</span>'
                : '';

            return '<div class="flex flex-col gap-1 rounded-lg border border-gray-200 bg-gray-50 p-3 dark:border-white/10 dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">'
                .'<div class="min-w-0">'
                .$slot
                .'<span class="text-sm font-medium text-gray-700 dark:text-gray-200">'.e($item['label']).'</span>'
                .'</div>'
                .'<div class="text-sm text-gray-900 dark:text-white sm:text-right">'.e($item['value']).'</div>'
                .'</div>';
        })->implode('');

        return new HtmlString('<div class="grid gap-2">'.$rows.'</div>');
    }
}

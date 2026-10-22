@php
    $data = $this->analyticsData();
    $connected = $data['connected'] ?? [];
    $usesMeta = (bool) ($connected['uses_meta'] ?? false);
    $currency = (string) ($connected['currency'] ?? 'INR');
    $showMessageCost = \App\Support\CrmAccess::canSeeMessageCost(auth()->user());
    $categories = $connected['categories'] ?? [];
    $sources = $connected['sources'] ?? [];
    $staffRows = $connected['staff'] ?? [];
    $dayRows = $data['by_day'] ?? [];
    $maxDayCost = 0.0;

    foreach ($dayRows as $day) {
        $maxDayCost = max($maxDayCost, (float) ($usesMeta ? ($day['meta_cost'] ?? 0) : ($day['crm_cost_inr'] ?? 0)));
    }

    $toneFor = function (string $category): string {
        return match (strtoupper($category)) {
            'MARKETING' => '#d97706',
            'UTILITY' => '#2563eb',
            'AUTHENTICATION', 'AUTHENTICATION_INTERNATIONAL' => '#7c3aed',
            'SERVICE' => '#059669',
            default => '#6b7280',
        };
    };
@endphp

<style>
    .crm-wa-cost { display: grid; gap: 16px; }
    .crm-wa-cost__hero {
        display: flex; flex-wrap: wrap; align-items: flex-end; justify-content: space-between; gap: 16px;
        padding: 20px; border-radius: 20px; color: #fff;
        background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 55%, #0f766e 100%);
    }
    .crm-wa-cost__amount { font-size: 2rem; font-weight: 700; letter-spacing: -0.03em; line-height: 1; }
    .crm-wa-cost__kicker { font-size: 12px; font-weight: 600; letter-spacing: 0.04em; text-transform: uppercase; opacity: 0.75; }
    .crm-wa-cost__note { margin-top: 8px; max-width: 36rem; font-size: 13px; line-height: 1.45; opacity: 0.9; }
    .crm-wa-cost__side { text-align: right; }
    .crm-wa-cost__side strong { display: block; font-size: 1.25rem; }
    .crm-wa-cost__cards { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 12px; }
    .crm-wa-cost__card {
        padding: 14px; border-radius: 16px; background: #fff;
        border: 1px solid rgba(15, 23, 42, 0.08); box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }
    .dark .crm-wa-cost__card { background: rgba(17, 24, 39, 0.72); border-color: rgba(255, 255, 255, 0.08); }
    .crm-wa-cost__card-top { display: flex; align-items: center; justify-content: space-between; gap: 8px; }
    .crm-wa-cost__dot { width: 8px; height: 8px; border-radius: 99px; }
    .crm-wa-cost__card-value { margin-top: 10px; font-size: 1.25rem; font-weight: 700; color: #0f172a; }
    .dark .crm-wa-cost__card-value { color: #fff; }
    .crm-wa-cost__card-meta { margin-top: 2px; font-size: 12px; color: #6b7280; }
    .crm-wa-cost__card-note { margin-top: 8px; font-size: 12px; line-height: 1.4; color: #4b5563; }
    .dark .crm-wa-cost__card-note { color: #d1d5db; }
    .crm-wa-cost__track { margin-top: 10px; height: 6px; border-radius: 99px; background: #e5e7eb; overflow: hidden; }
    .dark .crm-wa-cost__track { background: rgba(255, 255, 255, 0.08); }
    .crm-wa-cost__track > span { display: block; height: 100%; border-radius: 99px; }
    .crm-wa-cost__day { width: 88px; height: 8px; border-radius: 99px; background: #e5e7eb; overflow: hidden; }
    .dark .crm-wa-cost__day { background: rgba(255, 255, 255, 0.08); }
    .crm-wa-cost__day > span { display: block; height: 100%; background: #0f766e; }
    @media (max-width: 1024px) {
        .crm-wa-cost__cards { grid-template-columns: 1fr 1fr; }
        .crm-wa-cost__side { text-align: left; }
    }
    @media (max-width: 640px) {
        .crm-wa-cost__cards { grid-template-columns: 1fr; }
    }
</style>

<div class="crm-wa-analytics crm-wa-cost">
    <section class="crm-wa-analytics__filters rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Dates</h2>
                <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">Showing {{ $this->rangeLabel() }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Pick a quick range, or choose a from date and a to date.</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time'] as $key => $label)
                    <button type="button" wire:click="applyPreset('{{ $key }}')" @class(['crm-wa-analytics__preset', 'crm-wa-analytics__preset--active' => $datePreset === $key])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2">
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">From date</span>
                <input type="date" wire:model.live="dateFrom" class="crm-wa-analytics__date-input" />
            </label>
            <label class="block text-sm">
                <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">To date</span>
                <input type="date" wire:model.live="dateTo" class="crm-wa-analytics__date-input" />
            </label>
        </div>
    </section>

    @if (($data['meta']['status'] ?? '') === 'failed')
        <p class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
            WhatsApp did not return the bill ({{ $data['meta']['error'] ?? 'unavailable' }}). The numbers below are the school log only.
        </p>
    @endif

    <section class="crm-wa-cost__hero">
        <div>
            <p class="crm-wa-cost__kicker">{{ $usesMeta ? 'WhatsApp bill' : 'School log estimate' }}</p>
            @if ($showMessageCost)
                <p class="crm-wa-cost__amount">{{ $this->formatMoney((float) ($connected['total_cost'] ?? 0), $currency) }}</p>
            @else
                <p class="crm-wa-cost__amount">{{ number_format((int) ($connected['total_volume'] ?? 0)) }}</p>
            @endif
            <p class="crm-wa-cost__note">
                @if ($usesMeta)
                    This is the charge WhatsApp reported for these dates. The cards and tables split that same charge. A failed message is not added.
                @else
                    WhatsApp’s bill could not be loaded, so this is only what the school log recorded.
                @endif
            </p>
        </div>
        <div class="crm-wa-cost__side">
            <span class="crm-wa-cost__kicker">Messages WhatsApp counted</span>
            <strong>{{ number_format((int) ($connected['total_volume'] ?? 0)) }}</strong>
            <span class="crm-wa-cost__kicker">In the school log</span>
            <strong>{{ number_format((int) ($data['local']['total_messages'] ?? 0)) }}</strong>
        </div>
    </section>

    @if ($categories !== [])
        <section class="crm-wa-cost__cards" aria-label="WhatsApp categories">
            @foreach ($categories as $category)
                @php $tone = $toneFor((string) $category['key']); @endphp
                <article class="crm-wa-cost__card">
                    <div class="crm-wa-cost__card-top">
                        <span class="text-sm font-semibold text-gray-950 dark:text-white">{{ $this->categoryLabel((string) $category['key']) }}</span>
                        <span class="crm-wa-cost__dot" style="background: {{ $tone }}"></span>
                    </div>
                    @if ($showMessageCost)
                        <p class="crm-wa-cost__card-value">{{ $this->formatMoney((float) $category['cost'], $currency) }}</p>
                    @else
                        <p class="crm-wa-cost__card-value">{{ number_format((int) $category['volume']) }}</p>
                    @endif
                    <p class="crm-wa-cost__card-meta">{{ number_format((int) $category['volume']) }} messages · {{ number_format((float) $category['share'], 1) }}% of the bill</p>
                    <p class="crm-wa-cost__card-note">{{ $this->categoryNote((string) $category['key']) }}</p>
                    <div class="crm-wa-cost__track" aria-hidden="true"><span style="width: {{ min(100, (float) $category['share']) }}%; background: {{ $tone }}"></span></div>
                </article>
            @endforeach
        </section>
    @endif

    <section class="crm-wa-analytics__panel">
        <div class="mb-4">
            <h3 class="crm-wa-analytics__panel-title">Day by day</h3>
            <p class="mt-1 text-xs text-gray-500">Newest first. The bar is that day’s share of the {{ $usesMeta ? 'WhatsApp bill' : 'school log' }}.</p>
        </div>
        @if ($dayRows === [])
            <p class="text-sm text-gray-500">No messages in these dates.</p>
        @else
            <x-crm.responsive-table>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="py-2 pr-3">Date</th>
                            <th class="py-2 pr-3">{{ $usesMeta ? 'WhatsApp messages' : 'Messages' }}</th>
                            @if ($showMessageCost)
                                <th class="py-2 pr-3">{{ $usesMeta ? 'WhatsApp bill' : 'Estimate' }}</th>
                                <th class="py-2 pr-3">Share</th>
                            @endif
                            <th class="py-2 pr-3">School log</th>
                            <th class="py-2">Failed</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($dayRows as $day)
                            @php
                                $dayCost = (float) ($usesMeta ? ($day['meta_cost'] ?? 0) : ($day['crm_cost_inr'] ?? 0));
                                $dayWidth = $maxDayCost > 0 ? min(100, ($dayCost / $maxDayCost) * 100) : 0;
                            @endphp
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="crm-responsive-table__title py-2 pr-3 font-medium whitespace-nowrap" data-label="">{{ $day['date_label'] }}</td>
                                <td class="py-2 pr-3 tabular-nums" data-label="Messages">{{ number_format((int) ($usesMeta ? ($day['meta_volume'] ?? 0) : ($day['crm_messages'] ?? 0))) }}</td>
                                @if ($showMessageCost)
                                    <td class="py-2 pr-3 tabular-nums font-semibold" data-label="Bill">{{ $this->formatMoney($dayCost, $currency) }}</td>
                                    <td class="py-2 pr-3" data-label="Share"><div class="crm-wa-cost__day"><span style="width: {{ $dayWidth }}%"></span></div></td>
                                @endif
                                <td class="py-2 pr-3 tabular-nums" data-label="School log">{{ number_format((int) ($day['crm_messages'] ?? 0)) }}</td>
                                <td class="py-2 tabular-nums" data-label="Failed">{{ number_format((int) ($day['crm_failed'] ?? 0)) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        @endif
    </section>

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="crm-wa-analytics__panel">
            <h3 class="crm-wa-analytics__panel-title">Cost by source</h3>
            <p class="mt-1 text-xs text-gray-500">Each row is a school feature. The rupee is its share of the WhatsApp bill, not a second estimate.</p>
            @if ($sources === [])
                <p class="mt-4 text-sm text-gray-500">No messages in this period.</p>
            @else
                <x-crm.responsive-table>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-3">Feature</th>
                                <th class="py-2 pr-3">Messages</th>
                                <th class="py-2 pr-3">Category</th>
                                @if ($showMessageCost)
                                    <th class="py-2">Share of bill</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sources as $source)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="crm-responsive-table__title py-2 pr-3 font-medium" data-label="">{{ $this->sourceLabel((string) $source['key']) }}</td>
                                    <td class="py-2 pr-3 tabular-nums" data-label="Messages">{{ number_format((int) $source['count']) }}@if ((int) $source['failed'] > 0)<span class="text-xs text-gray-500"> · {{ number_format((int) $source['failed']) }} failed</span>@endif</td>
                                    <td class="py-2 pr-3" data-label="Category">{{ collect($source['categories'])->map(fn ($category) => $this->categoryLabel((string) $category))->implode(', ') ?: '—' }}</td>
                                    @if ($showMessageCost)
                                        <td class="py-2 tabular-nums font-semibold" data-label="Share of bill">{{ $this->formatMoney((float) $source['cost'], $currency) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                            @if ($showMessageCost && (float) ($connected['unlogged_cost'] ?? 0) > 0)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="py-2 pr-3 font-medium" data-label="">Not in the school log</td>
                                    <td class="py-2 pr-3 text-gray-500" data-label="Messages">WhatsApp billed these</td>
                                    <td class="py-2 pr-3" data-label="Category">—</td>
                                    <td class="py-2 tabular-nums font-semibold" data-label="Share of bill">{{ $this->formatMoney((float) $connected['unlogged_cost'], $currency) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </x-crm.responsive-table>
            @endif
        </section>

        <section class="crm-wa-analytics__panel">
            <h3 class="crm-wa-analytics__panel-title">Staff usage</h3>
            <p class="mt-1 text-xs text-gray-500">Only messages a person sent by hand. Automatic alerts and login codes stay out of this list.</p>
            @if ($staffRows === [])
                <p class="mt-4 text-sm text-gray-500">No staff sends in this period.</p>
            @else
                <x-crm.responsive-table>
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-3">Staff</th>
                                <th class="py-2 pr-3">Messages</th>
                                @if ($showMessageCost)
                                    <th class="py-2">Share of bill</th>
                                @endif
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($staffRows as $row)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="crm-responsive-table__title py-2 pr-3 font-medium" data-label="">{{ $row['name'] ?? '—' }}</td>
                                    <td class="py-2 pr-3 tabular-nums" data-label="Messages">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                                    @if ($showMessageCost)
                                        <td class="py-2 tabular-nums font-semibold" data-label="Share of bill">{{ $this->formatMoney((float) ($row['cost'] ?? $row['cost_inr'] ?? 0), $currency) }}</td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-crm.responsive-table>
            @endif
        </section>
    </div>

    <section class="crm-wa-analytics__panel">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="crm-wa-analytics__panel-title">Campaigns</h3>
            <span class="text-xs text-gray-500">{{ $data['from'] ?? '' }} → {{ $data['to'] ?? '' }}</span>
        </div>
        @if (($data['campaigns'] ?? []) === [])
            <p class="text-sm text-gray-500">No campaigns were sent in this date range.</p>
        @else
            <x-crm.responsive-table>
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="px-2 py-2">Campaign</th>
                            <th class="px-2 py-2">Template</th>
                            <th class="px-2 py-2">Category</th>
                            <th class="px-2 py-2">Sent</th>
                            <th class="px-2 py-2">Failed</th>
                            @if ($showMessageCost)
                                <th class="px-2 py-2">Share of bill</th>
                            @endif
                            <th class="px-2 py-2">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['campaigns'] as $campaign)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="crm-responsive-table__title px-2 py-2" data-label="">
                                    <a href="{{ $this->campaignViewUrl((int) $campaign['id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $campaign['name'] }}</a>
                                </td>
                                <td class="px-2 py-2" data-label="Template">{{ $campaign['template'] ?? '—' }}</td>
                                <td class="px-2 py-2" data-label="Category">{{ filled($campaign['category'] ?? null) ? $this->categoryLabel((string) $campaign['category']) : '—' }}</td>
                                <td class="px-2 py-2 tabular-nums" data-label="Sent">{{ number_format((int) $campaign['sent_count']) }}</td>
                                <td class="px-2 py-2 tabular-nums" data-label="Failed">{{ number_format((int) $campaign['failed_count']) }}</td>
                                @if ($showMessageCost)
                                    <td class="px-2 py-2 tabular-nums font-semibold" data-label="Share of bill">{{ $this->formatMoney((float) ($campaign['whatsapp_cost'] ?? 0), $currency) }}</td>
                                @endif
                                <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-500" data-label="When">{{ $campaign['shot_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        @endif
    </section>
</div>

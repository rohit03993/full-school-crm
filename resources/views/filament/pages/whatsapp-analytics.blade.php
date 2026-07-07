@php
    $data = $this->analyticsData();
    $meta = $data['meta'] ?? [];
    $local = $data['local'] ?? [];
    $metaOk = ($meta['status'] ?? '') === 'success';
    $displayCost = $metaOk ? (float) ($meta['total_cost'] ?? 0) : (float) ($local['total_cost_inr'] ?? 0);
    $displayVolume = $metaOk ? (int) ($meta['total_volume'] ?? 0) : (int) ($local['total_messages'] ?? 0);
    $currency = $metaOk ? (string) ($meta['currency'] ?? 'INR') : 'INR';
@endphp

<div class="crm-wa-analytics space-y-6">
    <section class="crm-wa-analytics__filters rounded-2xl border border-gray-200/70 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900/60">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h2 class="text-sm font-semibold text-gray-950 dark:text-white">Date range</h2>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Filter spend and message volume. Meta official cost loads when WABA ID is saved.</p>
            </div>

            <div class="flex flex-wrap gap-2">
                @foreach (['today' => 'Today', '7d' => '7 days', '30d' => '30 days', '90d' => '90 days', 'all' => 'All time', 'custom' => 'Custom'] as $key => $label)
                    <button
                        type="button"
                        wire:click="applyPreset('{{ $key }}')"
                        @class([
                            'crm-wa-analytics__preset',
                            'crm-wa-analytics__preset--active' => $datePreset === $key,
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($datePreset === 'custom')
            <div class="mt-4 grid gap-3 sm:grid-cols-3">
                <label class="block text-sm">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">From</span>
                    <input type="date" wire:model.live="dateFrom" class="crm-wa-analytics__date-input" />
                </label>
                <label class="block text-sm">
                    <span class="mb-1 block text-xs font-semibold uppercase tracking-wide text-gray-500">To</span>
                    <input type="date" wire:model.live="dateTo" class="crm-wa-analytics__date-input" />
                </label>
                <div class="flex items-end">
                    <button type="button" wire:click="refreshAnalytics" class="crm-wa-analytics__apply-btn">Apply</button>
                </div>
            </div>
        @endif
    </section>

    <section class="crm-meta-wa-log__stats">
        <div class="crm-meta-wa-stat crm-meta-wa-stat--total">
            <span class="crm-meta-wa-stat__value">{{ $this->formatMoney($displayCost, $currency) }}</span>
            <span class="crm-meta-wa-stat__label">{{ $metaOk ? 'Meta billed cost' : 'Estimated cost' }}</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--out">
            <span class="crm-meta-wa-stat__value">{{ number_format($displayVolume) }}</span>
            <span class="crm-meta-wa-stat__label">{{ $metaOk ? 'Delivered (Meta)' : 'Logged outbound' }}</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--in">
            <span class="crm-meta-wa-stat__value">{{ number_format((int) ($local['total_messages'] ?? 0)) }}</span>
            <span class="crm-meta-wa-stat__label">Messages in CRM log</span>
        </div>
        <div class="crm-meta-wa-stat crm-meta-wa-stat--delivered">
            <span class="crm-meta-wa-stat__value">{{ count($data['campaigns'] ?? []) }}</span>
            <span class="crm-meta-wa-stat__label">Campaigns in range</span>
        </div>
    </section>

    @if (($meta['status'] ?? '') === 'failed')
        <p class="rounded-xl border border-amber-200/80 bg-amber-50/80 px-4 py-3 text-sm text-amber-900 dark:border-amber-500/20 dark:bg-amber-500/10 dark:text-amber-100">
            Meta pricing API: {{ $meta['error'] ?? 'Unavailable' }}.
            Showing CRM estimates from Meta India rate card below.
        </p>
    @elseif ($metaOk)
        <p class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-4 py-3 text-sm text-emerald-900 dark:border-emerald-500/20 dark:bg-emerald-500/10 dark:text-emerald-100">
            Official spend from Meta <code class="text-xs">pricing_analytics</code> (India, per-message pricing).
            Per-campaign rows use CRM estimates until linked to Meta delivery data.
        </p>
    @endif

    <div class="grid gap-6 lg:grid-cols-2">
        <section class="crm-wa-analytics__panel">
            <h3 class="crm-wa-analytics__panel-title">Cost by category</h3>
            @php
                $categoryRows = $metaOk ? ($meta['by_category'] ?? []) : ($local['by_category'] ?? []);
            @endphp
            @if ($categoryRows === [])
                <p class="text-sm text-gray-500">No outbound messages in this period.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-3">Category</th>
                                <th class="py-2 pr-3">Volume</th>
                                <th class="py-2">Cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($categoryRows as $category => $row)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="py-2 pr-3 font-medium">{{ $this->categoryLabel((string) $category) }}</td>
                                    <td class="py-2 pr-3 tabular-nums">{{ number_format((int) ($row['volume'] ?? $row['count'] ?? 0)) }}</td>
                                    <td class="py-2 tabular-nums font-semibold">{{ $this->formatMoney((float) ($row['cost'] ?? $row['cost_inr'] ?? 0), $currency) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        <section class="crm-wa-analytics__panel">
            <h3 class="crm-wa-analytics__panel-title">Cost by source</h3>
            @php $sourceRows = $data['by_source'] ?? []; @endphp
            @if ($sourceRows === [])
                <p class="text-sm text-gray-500">No source breakdown yet.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs uppercase text-gray-500">
                                <th class="py-2 pr-3">Source</th>
                                <th class="py-2 pr-3">Messages</th>
                                <th class="py-2">Est. cost</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sourceRows as $source => $row)
                                <tr class="border-t border-gray-100 dark:border-white/10">
                                    <td class="py-2 pr-3 font-medium">{{ $this->sourceLabel((string) $source) }}</td>
                                    <td class="py-2 pr-3 tabular-nums">{{ number_format((int) ($row['count'] ?? 0)) }}</td>
                                    <td class="py-2 tabular-nums font-semibold">{{ $this->formatMoney((float) ($row['cost_inr'] ?? 0)) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    </div>

    <section class="crm-wa-analytics__panel">
        <div class="mb-4 flex items-center justify-between gap-3">
            <h3 class="crm-wa-analytics__panel-title">Campaigns in range</h3>
            <span class="text-xs text-gray-500">{{ $data['from'] ?? '' }} → {{ $data['to'] ?? '' }}</span>
        </div>

        @if (($data['campaigns'] ?? []) === [])
            <p class="text-sm text-gray-500">No campaigns were sent in this date range.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead>
                        <tr class="text-left text-xs uppercase text-gray-500">
                            <th class="px-2 py-2">Campaign</th>
                            <th class="px-2 py-2">Template</th>
                            <th class="px-2 py-2">Batch</th>
                            <th class="px-2 py-2">Sent</th>
                            <th class="px-2 py-2">Failed</th>
                            <th class="px-2 py-2">Est. cost</th>
                            <th class="px-2 py-2">When</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($data['campaigns'] as $campaign)
                            <tr class="border-t border-gray-100 dark:border-white/10">
                                <td class="px-2 py-2">
                                    <a href="{{ $this->campaignViewUrl((int) $campaign['id']) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $campaign['name'] }}
                                    </a>
                                </td>
                                <td class="px-2 py-2">{{ $campaign['template'] ?? '—' }}</td>
                                <td class="px-2 py-2">{{ $campaign['batch'] ?? '—' }}</td>
                                <td class="px-2 py-2 tabular-nums">{{ number_format((int) $campaign['sent_count']) }}</td>
                                <td class="px-2 py-2 tabular-nums">{{ number_format((int) $campaign['failed_count']) }}</td>
                                <td class="px-2 py-2 tabular-nums font-semibold">{{ $this->formatMoney((float) $campaign['estimated_total_cost_inr']) }}</td>
                                <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-500">{{ $campaign['shot_at'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>
</div>

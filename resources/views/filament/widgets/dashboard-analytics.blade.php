@php
    $kpis = $kpis ?? [];
    $trendTabs = $trendTabs ?? [];
    $trendColumns = $trendColumns ?? [];
    $mixRows = $mixRows ?? [];
    $funnelSteps = $funnelSteps ?? [];
@endphp

<x-filament-widgets::widget>
    <div class="crm-analytics" @if (! empty($poll)) wire:poll.{{ $poll }} @endif>
        <button
            type="button"
            class="crm-analytics__toggle"
            wire:click="toggleAnalytics"
            aria-expanded="{{ $showAnalytics ? 'true' : 'false' }}"
        >
            {{ $showAnalytics ? 'Hide analytics ▴' : 'Show analytics ▾' }}
        </button>

        @if ($showAnalytics)
            <div class="crm-analytics__panel">
                @if ($kpis !== [])
                    <div class="crm-analytics__hero">
                        @foreach ($kpis as $kpi)
                            @php $tag = filled($kpi['url'] ?? null) ? 'a' : 'div'; @endphp
                            <{{ $tag }}
                                class="crm-analytics-kpi crm-analytics-kpi--{{ $kpi['tone'] ?? 'primary' }}"
                                @if (filled($kpi['url'] ?? null)) href="{{ $kpi['url'] }}" wire:navigate @endif
                            >
                                <span class="crm-analytics-kpi__label">{{ $kpi['label'] }}</span>
                                <b class="crm-analytics-kpi__value">{{ $kpi['value'] }}</b>
                                <span class="crm-analytics-kpi__sub">{{ $kpi['sub'] }}</span>
                            </{{ $tag }}>
                        @endforeach
                    </div>
                @endif

                <div class="crm-analytics__grid">
                    <div class="crm-analytics-card crm-analytics-card--trend">
                        <div class="crm-analytics-card__head">
                            <p class="crm-analytics-card__title">Last 7 days</p>
                            @if ($trendTabs !== [])
                                <div class="crm-trend-toggle" role="tablist" aria-label="Chart metric">
                                    @foreach ($trendTabs as $tab)
                                        <button
                                            type="button"
                                            class="crm-trend-tab {{ $trendMetric === $tab['key'] ? 'is-active' : '' }}"
                                            wire:click="setTrendMetric('{{ $tab['key'] }}')"
                                            role="tab"
                                            aria-selected="{{ $trendMetric === $tab['key'] ? 'true' : 'false' }}"
                                        >
                                            {{ $tab['label'] }}
                                        </button>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        <div class="crm-trend-chart" aria-live="polite">
                            @foreach ($trendColumns as $col)
                                <div class="crm-trend-col {{ ! empty($col['is_today']) ? 'is-today' : '' }}">
                                    <div class="crm-trend-bar-wrap">
                                        <span class="crm-trend-bar" style="height: {{ $col['height'] }}%"></span>
                                    </div>
                                    <span class="crm-trend-val">{{ $col['display'] }}</span>
                                    <span class="crm-trend-day">{{ $col['day'] }}</span>
                                </div>
                            @endforeach
                        </div>
                        <p class="crm-analytics-card__foot">{{ $trendFoot }}</p>
                    </div>

                    @if ($mixRows !== [])
                        <div class="crm-analytics-card">
                            <p class="crm-analytics-card__title">Today’s mix</p>
                            @foreach ($mixRows as $row)
                                <div class="crm-mix-row">
                                    <div class="crm-mix-meta">
                                        <span>{{ $row['label'] }}</span>
                                        <strong>{{ $row['value'] }}</strong>
                                    </div>
                                    <div class="crm-mix-track" title="{{ $row['pct'] }}%">
                                        <span class="crm-mix-fill crm-mix-fill--{{ $row['tone'] }}" style="width: {{ $row['pct'] }}%"></span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($funnelSteps !== [])
                        <div class="crm-analytics-card crm-analytics-card--funnel">
                            <p class="crm-analytics-card__title">Needs attention</p>
                            <div class="crm-funnel">
                                @foreach ($funnelSteps as $step)
                                    @php $tag = filled($step['url'] ?? null) ? 'a' : 'div'; @endphp
                                    <{{ $tag }}
                                        class="crm-funnel-step"
                                        @if (filled($step['url'] ?? null)) href="{{ $step['url'] }}" wire:navigate @endif
                                    >
                                        <span class="crm-funnel-bar" style="width: {{ max(8, $step['pct']) }}%"></span>
                                        <span class="crm-funnel-label">{{ $step['label'] }}</span>
                                        <b class="crm-funnel-value">{{ $step['value'] }}</b>
                                    </{{ $tag }}>
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

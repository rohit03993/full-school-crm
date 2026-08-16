<x-filament-widgets::widget class="fi-dashboard-hero-widget">
    <div class="crm-hero">
        <div class="relative flex flex-wrap items-start justify-between gap-4">
            <div class="flex min-w-0 items-center gap-3">
                <span class="crm-hero__avatar">{{ $initials }}</span>
                <div class="min-w-0">
                    <p class="crm-hero__eyebrow">{{ $instituteName }}</p>
                    <h2 class="crm-hero__title">Welcome back, {{ $userName }}</h2>
                    <p class="crm-hero__meta">
                        {{ $todayLabel }}
                        @if ($tagline)
                            <span class="hidden sm:inline">· {{ $tagline }}</span>
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if ($sessionLabel)
                    <span class="crm-hero__scope">
                        <x-filament::icon icon="heroicon-m-academic-cap" class="h-3.5 w-3.5" />
                        {{ $sessionLabel }}
                    </span>
                @endif
                <span class="crm-hero__scope crm-hero__scope--active" title="{{ $scopeDates }}">
                    <x-filament::icon icon="heroicon-m-calendar-days" class="h-3.5 w-3.5" />
                    {{ $scopeLabel }}
                </span>
            </div>
        </div>

        @if (filled($metrics))
            <div class="crm-hero__kpis">
                @foreach ($metrics as $metric)
                    @php
                        $tag = filled($metric['url']) ? 'a' : 'div';
                    @endphp

                    <{{ $tag }}
                        class="crm-hero__kpi crm-hero__kpi--{{ $metric['tone'] }}"
                        @if (filled($metric['url'])) href="{{ $metric['url'] }}" wire:navigate @endif
                        @if (filled($metric['meta'])) title="{{ $metric['meta'] }}" @endif
                    >
                        <span class="crm-hero__kpi-label">
                            <x-filament::icon :icon="$metric['icon']" class="h-3.5 w-3.5 shrink-0" />
                            <span class="truncate">{{ $metric['label'] }}</span>
                        </span>
                        <span class="crm-hero__kpi-value">{{ $metric['value'] }}</span>
                        @if (filled($metric['meta']))
                            <span class="crm-hero__kpi-meta">{{ $metric['meta'] }}</span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        @endif

        @if (filled($quickActions))
            <div class="crm-hero__actions">
                @foreach ($quickActions as $action)
                    <a
                        href="{{ $action['url'] }}"
                        wire:navigate
                        class="crm-hero__action"
                        title="{{ $action['description'] }}"
                    >
                        <span class="crm-hero__action-icon">
                            <x-filament::icon :icon="$action['icon']" class="h-4 w-4" />
                        </span>
                        {{ $action['label'] }}
                    </a>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

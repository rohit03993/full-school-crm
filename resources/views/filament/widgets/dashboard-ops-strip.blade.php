@php
    $tiles = $tiles ?? [];
    $hideOnPhone = $hideOnPhone ?? false;
@endphp

<x-filament-widgets::widget>
    @if ($hideOnPhone)
        <style>
            @media (max-width: 1023px) {
                .fi-wi-widget:has(.crm-ops-strip--phone-off) {
                    display: none !important;
                }
            }
        </style>
    @endif
    @if ($tiles !== [])
        <div @class(['crm-ops-strip', 'crm-ops-strip--phone-off' => $hideOnPhone]) @if (! empty($poll)) wire:poll.{{ $poll }} @endif>
            <div class="crm-ops-strip__head">
                <div>
                    <p class="crm-ops-strip__heading">{{ $heading }}</p>
                    @if (! empty($subheading))
                        <p class="crm-ops-strip__sub">{{ $subheading }}</p>
                    @endif
                </div>
            </div>

            <div class="crm-ops-strip__grid">
                @foreach ($tiles as $tile)
                    @php $tag = filled($tile['url'] ?? null) ? 'a' : 'div'; @endphp
                    <{{ $tag }}
                        class="crm-ops-stat crm-ops-stat--{{ $tile['tone'] ?? 'neutral' }}"
                        @if (filled($tile['url'] ?? null)) href="{{ $tile['url'] }}" wire:navigate @endif
                        @if (filled($tile['meta'] ?? null)) title="{{ $tile['meta'] }}" @endif
                    >
                        <span class="crm-ops-stat__value">{{ $tile['value'] }}</span>
                        <span class="crm-ops-stat__label">{{ $tile['label'] }}</span>
                        @if (filled($tile['meta'] ?? null))
                            <span class="crm-ops-stat__meta">{{ $tile['meta'] }}</span>
                        @endif
                    </{{ $tag }}>
                @endforeach
            </div>
        </div>
    @endif
</x-filament-widgets::widget>

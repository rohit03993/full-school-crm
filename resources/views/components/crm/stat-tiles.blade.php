@props([
    'stats' => [],
])

{{-- Each stat: ['label' => string, 'value' => scalar, 'tone' => primary|info|success|warning|null] --}}
<div {{ $attributes->class('crm-stat-grid') }}>
    @foreach ($stats as $stat)
        <div @class([
            'crm-stat',
            'crm-stat--'.($stat['tone'] ?? '') => filled($stat['tone'] ?? null),
        ])>
            <p class="crm-stat__value">{{ $stat['value'] ?? 0 }}</p>
            <p class="crm-stat__label">{{ $stat['label'] ?? '' }}</p>
        </div>
    @endforeach
</div>

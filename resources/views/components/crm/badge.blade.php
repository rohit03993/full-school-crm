@props([
    'tone' => 'gray',
])

@php
    $tones = ['gray', 'success', 'info', 'warning', 'danger'];
    $tone = in_array($tone, $tones, true) ? $tone : 'gray';
@endphp

<span {{ $attributes->class(['crm-badge', 'crm-badge--'.$tone]) }}>
    {{ $slot }}
</span>

@props([
    'studentId' => null,
    'name' => '',
])

@php
    $id = is_numeric($studentId) ? (int) $studentId : null;
    $url = \App\Support\CrmPersonLink::studentUrl($id);
    $label = filled($name) ? (string) $name : '—';
@endphp

@if ($url)
    <a
        href="{{ $url }}"
        wire:navigate
        {{ $attributes->class(['crm-person-name font-semibold text-primary-700 hover:underline dark:text-primary-300']) }}
    >{{ $label }}</a>
@else
    <span {{ $attributes->class(['font-semibold text-gray-950 dark:text-white']) }}>{{ $label }}</span>
@endif

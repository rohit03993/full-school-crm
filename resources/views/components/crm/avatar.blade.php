@props([
    'name' => null,
])

@php
    $words = preg_split('/\s+/', trim((string) $name), -1, PREG_SPLIT_NO_EMPTY) ?: [];

    $initials = match (count($words)) {
        0 => '—',
        1 => mb_substr($words[0], 0, 2),
        default => mb_substr($words[0], 0, 1).mb_substr($words[count($words) - 1], 0, 1),
    };
@endphp

<span {{ $attributes->class('crm-avatar') }} aria-hidden="true">{{ $initials }}</span>

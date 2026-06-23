@php
    use App\Support\MeetingForOptions;

    $value = $value ?? ($meetingFor ?? null);
    $size = $size ?? 'md';

    if (blank($value)) {
        return;
    }

    $value = (string) $value;
    $colors = MeetingForOptions::badgeStyle($value);
@endphp

<span @class([
    'inline-flex items-center gap-1.5 font-bold uppercase tracking-wide ring-1',
    $colors['bg'],
    $colors['text'],
    $colors['ring'],
    'rounded-md px-2 py-0.5 text-[10px]' => $size === 'sm',
    'rounded-lg px-3 py-1.5 text-xs' => $size === 'md',
    'rounded-xl px-4 py-2 text-sm' => $size === 'lg',
])>
    <x-filament::icon :icon="$colors['icon']" @class([
        'h-3 w-3' => $size === 'sm',
        'h-4 w-4' => $size === 'md',
        'h-5 w-5' => $size === 'lg',
    ]) />
    {{ MeetingForOptions::label($value) }}
</span>

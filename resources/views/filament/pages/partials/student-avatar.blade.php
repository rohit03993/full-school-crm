@php
    $size = $size ?? 'sm';
    $photo = $student->profilePhoto();
    $initials = $student->initials() ?: '?';

    $boxClass = match ($size) {
        'md' => 'h-12 w-12 rounded-xl text-sm',
        default => 'h-9 w-9 rounded-lg text-[11px]',
    };
@endphp

<div @class([
    'flex shrink-0 items-center justify-center overflow-hidden bg-primary-500/15 font-bold text-primary-700 dark:text-primary-300',
    $boxClass,
])>
    @if ($photo && $photo->isImage())
        <img
            src="{{ $photo->previewUrl() }}"
            alt="{{ $student->name }}"
            class="h-full w-full object-cover"
            loading="lazy"
        />
    @else
        {{ $initials }}
    @endif
</div>

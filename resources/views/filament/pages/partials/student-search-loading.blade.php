@php
    $mobile = $mobile ?? '';
    $mode = $mode ?? 'mobile';
    $isNameSearch = $mode === 'name';
@endphp

<div
    class="fi-student-search-loading flex items-center gap-3 rounded-xl border border-primary-200/70 bg-primary-50/60 px-4 py-3 dark:border-primary-500/20 dark:bg-primary-500/10"
    role="status"
    aria-live="polite"
    aria-busy="true"
>
    <span class="relative flex h-9 w-9 shrink-0 items-center justify-center">
        <span class="absolute inset-0 animate-ping rounded-full bg-primary-400/30"></span>
        <span class="relative flex h-8 w-8 items-center justify-center rounded-full bg-primary-500/15">
            <x-filament::icon
                icon="heroicon-o-magnifying-glass"
                class="h-4 w-4 animate-pulse text-primary-600 dark:text-primary-400"
            />
        </span>
    </span>
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-gray-950 dark:text-white">
            {{ $isNameSearch ? 'Finding matches…' : 'Looking up mobile…' }}
            @if (filled($mobile))
                <span @class(['text-primary-600 dark:text-primary-400', 'font-mono' => ! $isNameSearch])>{{ $mobile }}</span>
            @endif
        </p>
    </div>
</div>

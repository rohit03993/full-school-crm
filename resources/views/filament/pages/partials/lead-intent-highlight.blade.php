@php
    use App\Support\MeetingForOptions;

    $intent = $leadSources['latest_intent'] ?? null;
    $meetingForValue = $leadSources['latest_meeting_for'] ?? null;
    $source = $leadSources['latest_source'] ?? null;

    if (blank($intent) || blank($meetingForValue)) {
        return;
    }

    $meetingColors = MeetingForOptions::badgeStyle((string) $meetingForValue);
@endphp

<div @class([
    'mt-3 rounded-xl border-2 px-4 py-3 shadow-sm',
    $meetingColors['ring'],
    $meetingColors['bg'],
])>
    <p class="text-[10px] font-bold uppercase tracking-widest text-gray-500 dark:text-gray-400">
        Meeting for
    </p>
    <div class="mt-2 flex flex-wrap items-center gap-2">
        @if ($source?->value === 'walk_in')
            <span class="inline-flex items-center gap-1 rounded-lg bg-sky-500/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-sky-900 ring-1 ring-sky-500/30 dark:text-sky-300">
                Walk-in
            </span>
            <span class="text-lg font-black text-gray-400 dark:text-gray-500">→</span>
        @elseif ($source?->value === 'website')
            <span class="inline-flex items-center gap-1 rounded-lg bg-emerald-500/20 px-2.5 py-1 text-xs font-bold uppercase tracking-wide text-emerald-900 ring-1 ring-emerald-500/30 dark:text-emerald-300">
                Website
            </span>
            <span class="text-lg font-black text-gray-400 dark:text-gray-500">→</span>
        @endif

        <span @class([
            'inline-flex items-center gap-2 rounded-xl px-4 py-2 text-base font-black uppercase tracking-wide ring-2 sm:text-lg',
            $meetingColors['bg'],
            $meetingColors['text'],
            $meetingColors['ring'],
        ])>
            <x-filament::icon :icon="$meetingColors['icon']" class="h-5 w-5 sm:h-6 sm:w-6" />
            {{ MeetingForOptions::label((string) $meetingForValue) }}
        </span>
    </div>
    <p class="mt-2 text-sm font-semibold text-gray-700 dark:text-gray-300">
        {{ $intent }}
    </p>
</div>

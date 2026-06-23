@php
    $compact = $compact ?? false;
@endphp

@if ($record->last_call_at)
    <div @class([
        'rounded-lg bg-gray-50 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-400',
        'mt-1.5 px-2.5 py-1.5' => $compact,
        'mt-3 px-3 py-2' => ! $compact,
    ])>
        <span class="font-medium text-gray-700 dark:text-gray-300">Last call</span>
        {{ $record->last_call_at->format('d M Y, h:i A') }}
        @if ($record->lastCall?->staff)
            <span class="text-gray-400 dark:text-gray-500">·</span>
            by {{ $record->lastCall->staff->name }}
        @endif
        @if ($record->last_call_status)
            <span class="text-gray-400 dark:text-gray-500">·</span>
            {{ $record->last_call_status->label() }}
        @endif
        @if (filled($record->last_call_notes) && ! $compact)
            <p class="mt-1 text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::limit($record->last_call_notes, 120) }}</p>
        @endif
        @if ($record->next_call_followup_at && $record->next_call_followup_at->isPast())
            <span class="ml-1 inline-flex rounded-md bg-danger-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-danger-700 dark:text-danger-400">
                Callback overdue
            </span>
        @elseif ($record->next_call_followup_at?->isToday())
            <span class="ml-1 inline-flex rounded-md bg-warning-500/10 px-1.5 py-0.5 text-[10px] font-bold uppercase text-warning-700 dark:text-warning-400">
                Callback today
            </span>
        @endif
    </div>
@elseif (! $compact && (int) $record->total_calls === 0 && filled($record->mobile))
    <p class="mt-2 text-xs text-gray-400 dark:text-gray-500">Not called yet</p>
@endif

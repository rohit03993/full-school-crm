@if (! empty($meetingAssignment))
    <div @class([
        'mt-3 rounded-xl px-3 py-2.5 ring-1',
        'bg-amber-50 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/30' => $meetingAssignment['is_mine'],
        'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => ! $meetingAssignment['is_mine'],
    ])>
        <div class="flex flex-wrap items-start justify-between gap-2">
            <div class="min-w-0 flex-1">
                <p class="text-[10px] font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                    Meeting assigned
                </p>
                <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
                    @if ($meetingAssignment['is_mine'])
                        Assigned to you
                    @else
                        {{ $meetingAssignment['staff_name'] }}
                    @endif
                    <span class="font-normal text-gray-500 dark:text-gray-400">
                        · {{ $meetingAssignment['assigned_at']?->format('d M Y H:i') }}
                    </span>
                </p>
                <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                    By {{ $meetingAssignment['assigned_by_name'] }}
                    @if (filled($meetingAssignment['course_name'] ?? null))
                        · {{ $meetingAssignment['course_name'] }}
                    @endif
                </p>
                @if (filled($meetingAssignment['handoff_notes'] ?? null))
                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">
                        <span class="font-semibold">Handoff:</span> {{ $meetingAssignment['handoff_notes'] }}
                    </p>
                @endif
            </div>

            @if ($meetingAssignment['can_close'] ?? false)
                <x-filament::button
                    size="sm"
                    color="success"
                    wire:click="openCloseMeetingModal"
                >
                    Close the meeting
                </x-filament::button>
            @endif
        </div>
    </div>
@endif

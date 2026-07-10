<div>
    @if (! $visitsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading visit timeline…</p>
    @else
        @if ($record->next_call_followup_at)
            <div @class([
                'mb-4 rounded-xl px-4 py-3 ring-1',
                'bg-amber-50 ring-amber-200 dark:bg-amber-500/10 dark:ring-amber-500/20' => $record->next_call_followup_at->toDateString() <= today()->toDateString(),
                'bg-sky-50 ring-sky-200 dark:bg-sky-500/10 dark:ring-sky-500/20' => $record->next_call_followup_at->toDateString() > today()->toDateString(),
            ])>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Next call follow-up</p>
                <p class="mt-1 text-sm font-bold text-gray-950 dark:text-white">
                    {{ $record->next_call_followup_at->format('d M Y, h:i A') }}
                </p>
                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">
                    @if ($record->next_call_followup_at->toDateString() < today()->toDateString())
                        Overdue — should appear in the call queue
                    @elseif ($record->next_call_followup_at->isToday())
                        Due today — appears in the telecaller queue
                    @else
                        Scheduled — will appear in the queue on {{ $record->next_call_followup_at->format('d M Y') }}
                    @endif
                </p>
            </div>
        @endif

        @if ($leadTimeline->isEmpty())
            <p class="px-1 text-sm text-gray-500 dark:text-gray-400">No visits or calls recorded yet.</p>
        @else
            <div>
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Activity timeline</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Campus visits and phone calls in one place, newest first.</p>
                <div class="mt-3 space-y-2">
                    @foreach ($leadTimeline as $item)
                        <div @class([
                            'rounded-xl px-4 py-3 ring-1',
                            'bg-white ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10' => $item['type'] !== 'call',
                            'bg-sky-50/80 ring-sky-200/80 dark:bg-sky-500/5 dark:ring-sky-500/20' => $item['type'] === 'call',
                        ])>
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $item['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $item['occurred_at']->format('d M Y, h:i A') }}
                                        @if ($item['staff_name'])
                                            · {{ $item['staff_name'] }}
                                        @endif
                                    </p>
                                </div>
                                @if ($item['status_label'])
                                    <span @class([
                                        'shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold',
                                        'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300' => $item['type'] === 'call',
                                        'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' => $item['type'] !== 'call',
                                    ])>
                                        {{ $item['status_label'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item['summary'] }}</p>
                            @if ($item['detail'])
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $item['detail'] }}</p>
                            @endif
                            @if ($item['follow_up_at'])
                                <p class="mt-1 text-xs font-medium text-amber-700 dark:text-amber-300">
                                    Follow-up: {{ $item['follow_up_at']->format('d M Y, h:i A') }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    @endif
</div>

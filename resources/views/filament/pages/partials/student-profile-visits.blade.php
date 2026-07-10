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

        @if ($leadTimeline->isNotEmpty())
            <div class="mb-6">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Lead timeline</h3>
                <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Visits and calls in one place, newest first.</p>
                <div class="mt-3 space-y-2">
                    @foreach ($leadTimeline as $item)
                        <div class="rounded-xl bg-white px-4 py-3 ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
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
                                    <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                        {{ $item['status_label'] }}
                                    </span>
                                @endif
                            </div>
                            <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $item['summary'] }}</p>
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

        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Visit log</h3>

        @if ($visits->isEmpty())
            <p class="mt-2 px-1 text-sm text-gray-500 dark:text-gray-400">No visits recorded yet.</p>
        @else
            <div class="mt-3 space-y-3">
                @foreach ($visits as $visit)
                    <div class="rounded-xl bg-gray-50 px-4 py-3.5 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex items-start justify-between gap-2">
                            <p class="text-sm font-bold text-gray-950 dark:text-white">
                                @if ($visit->isCampusVisit())
                                    Campus visit
                                @else
                                    Visit #{{ $visitSequenceById[$visit->id] ?? '—' }}
                                @endif
                                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $visit->visit_date?->format('d M Y') }}</span>
                            </p>
                            <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                {{ $visit->displayStatusLabel() }}
                            </span>
                        </div>
                        <p class="mt-1 truncate text-xs font-medium text-gray-600 dark:text-gray-400">
                            @if ($visit->isCampusVisit())
                                @if ($visit->campus_purpose)
                                    {{ $visit->campus_purpose->label() }}
                                @endif
                            @else
                                {{ $visit->enquiry?->course?->name ?? 'General visit' }}
                            @endif
                        </p>
                        <p class="mt-2 text-sm leading-relaxed text-gray-700 dark:text-gray-300">{{ $visit->discussion_summary }}</p>
                        @if ($visit->remarks)
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $visit->remarks }}</p>
                        @endif
                        <p class="mt-2 text-xs text-gray-400">
                            {{ $visit->staff?->name ?? '—' }}
                            @if ($visit->next_follow_up_date)
                                · Follow-up {{ $visit->next_follow_up_date->format('d M Y') }}
                            @endif
                        </p>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>

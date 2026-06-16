<div>
    @if (! $visitsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading visit timeline…</p>
    @elseif ($visits->isEmpty())
        <p class="px-1 text-sm text-gray-500 dark:text-gray-400">No visits recorded yet.</p>
    @else
        <div class="space-y-3">
            @foreach ($visits as $visit)
                <div class="rounded-xl bg-gray-50 px-4 py-3.5 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">
                            {{ $visit->visit_date?->format('d M Y') }}
                        </p>
                        <span class="shrink-0 rounded-full bg-primary-50 px-2 py-0.5 text-[10px] font-semibold text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                            {{ $visit->status?->label() }}
                        </span>
                    </div>
                    <p class="mt-1 truncate text-xs font-medium text-gray-600 dark:text-gray-400">
                        {{ $visit->enquiry?->course?->name ?? 'General visit' }}
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
</div>

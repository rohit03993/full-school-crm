@if (! $homeworkTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading homework…</p>
@else
    <div class="space-y-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Homework for this student, grouped by date.</p>

        @if (($notDoneThisWeek ?? 0) > 0)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                <span class="font-semibold">{{ $notDoneThisWeek }}</span> Not Done mark(s) this week.
            </div>
        @endif

        @if (($days ?? []) === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No homework for this student yet.</p>
        @else
            <div class="space-y-4">
                @foreach ($days as $day)
                    <section class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-white/5">
                        <div class="flex min-w-0 flex-wrap items-baseline justify-between gap-2 border-b border-gray-100 bg-gray-50 px-4 py-2.5 dark:border-white/5 dark:bg-white/5">
                            <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $day['date_label'] }}</p>
                            @if (filled($day['class_label'] ?? null))
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $day['class_label'] }}</p>
                            @endif
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($day['subjects'] as $row)
                                <li class="flex min-w-0 flex-wrap items-start justify-between gap-3 px-4 py-3">
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['subject'] }}</p>
                                        @if (filled($row['title']) && $row['title'] !== $row['subject'])
                                            <p class="mt-0.5 text-sm text-gray-700 dark:text-gray-200">{{ $row['title'] }}</p>
                                        @endif
                                        @if (filled($row['description']))
                                            <p class="mt-0.5 line-clamp-2 whitespace-pre-line text-xs text-gray-500 dark:text-gray-400">{{ $row['description'] }}</p>
                                        @endif
                                        @if (filled($row['public_url']))
                                            <a
                                                href="{{ $row['public_url'] }}"
                                                target="_blank"
                                                rel="noopener"
                                                class="mt-1 inline-block text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400"
                                            >
                                                Open homework
                                            </a>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 flex-col items-end gap-1">
                                        @if ($row['link_tracked'])
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => $row['opened'],
                                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! $row['opened'],
                                            ])>
                                                {{ $row['opened'] ? 'Opened' : 'Not opened' }}
                                            </span>
                                            @if ($row['opened'] && filled($row['opened_at'] ?? null))
                                                <span class="text-[11px] text-gray-400">{{ $row['opened_at'] }}</span>
                                            @endif
                                        @endif
                                        @if (filled($row['check_status']))
                                            <span @class([
                                                'inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                                'bg-rose-100 text-rose-700 dark:bg-rose-500/10 dark:text-rose-300' => $row['check_is_not_done'],
                                                'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300' => ! $row['check_is_not_done'],
                                            ])>
                                                {{ $row['check_status'] }}
                                            </span>
                                        @endif
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </div>
@endif

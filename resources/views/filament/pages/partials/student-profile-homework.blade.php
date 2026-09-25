@if (! $homeworkTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading homework…</p>
@else
    <div class="space-y-4">
        <p class="text-xs text-gray-500 dark:text-gray-400">Homework for this student, grouped by date. Tap a date to open it.</p>

        @if (($notDoneThisWeek ?? 0) > 0)
            <div class="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800 dark:border-rose-500/20 dark:bg-rose-500/10 dark:text-rose-200">
                <span class="font-semibold">{{ $notDoneThisWeek }}</span> Not Done mark(s) this week.
            </div>
        @endif

        @if (($days ?? []) === [])
            <p class="text-sm text-gray-500 dark:text-gray-400">No homework for this student yet.</p>
        @else
            @php
                $firstDate = (string) ($days[0]['date'] ?? '');
            @endphp
            <div
                class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900"
                x-data="{ openDate: @js($firstDate) }"
            >
                @foreach ($days as $day)
                    @php
                        $dateKey = (string) ($day['date'] ?? '');
                        $subjectCount = count($day['subjects'] ?? []);
                    @endphp
                    <div
                        class="border-b border-gray-100 last:border-b-0 dark:border-white/5"
                        x-bind:class="openDate === @js($dateKey) ? 'bg-primary-50/40 dark:bg-primary-500/10' : ''"
                    >
                        <button
                            type="button"
                            class="flex w-full min-w-0 items-center gap-2 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                            x-on:click="openDate = openDate === @js($dateKey) ? '' : @js($dateKey)"
                        >
                            <svg
                                class="h-4 w-4 shrink-0 text-gray-400 transition-transform"
                                x-bind:class="openDate === @js($dateKey) ? 'rotate-90' : ''"
                                viewBox="0 0 20 20"
                                fill="currentColor"
                                aria-hidden="true"
                            >
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 0 1 .02-1.06L11.17 10 7.23 6.29a.75.75 0 0 1 1.04-1.08l4.5 4.25a.75.75 0 0 1 0 1.08l-4.5 4.25a.75.75 0 0 1-1.06-.02Z" clip-rule="evenodd" />
                            </svg>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $day['date_label'] }}</span>
                                @if (filled($day['class_label'] ?? null))
                                    <span class="block text-xs text-gray-500 dark:text-gray-400">{{ $day['class_label'] }}</span>
                                @endif
                            </span>
                            <span class="shrink-0 text-xs font-medium text-gray-400 dark:text-gray-500">
                                {{ $subjectCount }} {{ $subjectCount === 1 ? 'subject' : 'subjects' }}
                            </span>
                        </button>

                        <ul
                            x-show="openDate === @js($dateKey)"
                            x-cloak
                            class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/5 dark:border-white/5"
                        >
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
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@endif

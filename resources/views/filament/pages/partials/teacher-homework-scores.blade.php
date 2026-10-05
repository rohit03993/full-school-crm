<div class="space-y-4">
    <div class="rounded-2xl border border-gray-200 bg-white p-4 shadow-sm dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period</p>
                <p class="mt-1 text-2xl font-bold tracking-tight text-gray-950 dark:text-white">
                    {{ $report['day_count'] }} {{ $report['day_count'] === 1 ? 'day' : 'days' }}
                </p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $report['period_label'] }}</p>
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Up to today. Each date counts once.</p>
            </div>
            <div class="flex flex-wrap items-end gap-3">
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                    From
                    <input
                        type="date"
                        wire:model.live="dateFrom"
                        max="{{ $maxDate }}"
                        class="mt-1 block rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                    />
                </label>
                <label class="block text-xs font-semibold text-gray-600 dark:text-gray-300">
                    To
                    <input
                        type="date"
                        wire:model.live="dateTo"
                        max="{{ $maxDate }}"
                        class="mt-1 block rounded-xl border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                    />
                </label>
            </div>
        </div>
    </div>

    @if ($showingOne)
        @if ($canSeeAll)
            <button
                type="button"
                wire:click="clearTeacher"
                class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400"
            >
                All teachers
            </button>
        @endif

        @forelse ($report['teachers'] as $teacher)
            <div class="space-y-4" wire:key="teacher-score-{{ $teacher['user_id'] }}">
                <div class="rounded-2xl border border-gray-200 bg-white p-5 shadow-sm dark:border-gray-700 dark:bg-gray-900">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $teacher['name'] }}</p>
                            <p class="mt-1 max-w-3xl text-sm text-gray-500 dark:text-gray-400">{{ $teacher['classes'] !== '' ? $teacher['classes'] : 'No subject assigned' }}</p>
                        </div>
                        <div class="text-right">
                            <p class="text-4xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $teacher['score'] === null ? '—' : $teacher['score'].'%' }}</p>
                            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500">
                                {{ $teacher['day_count'] }} {{ $teacher['day_count'] === 1 ? 'day' : 'days' }}
                            </p>
                        </div>
                    </div>
                    @if (filled($teacher['note'] ?? null))
                        <p class="mt-3 text-sm text-gray-500 dark:text-gray-400">{{ $teacher['note'] }}</p>
                    @endif
                    <div class="mt-4 grid gap-3 sm:grid-cols-2">
                        @foreach ($teacher['parts'] as $part)
                            <div class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/5">
                                <div class="flex items-center justify-between gap-2">
                                    <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $part['label'] }}</p>
                                    <p class="text-sm font-bold text-gray-950 dark:text-white">{{ $part['percent'] === null ? '—' : $part['percent'].'%' }}</p>
                                </div>
                                <p class="mt-1 text-xs text-gray-600 dark:text-gray-300">{{ $part['summary'] }}</p>
                                <p class="mt-2 text-xs text-gray-500 dark:text-gray-400">{{ $part['cause'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>

                @if ($teacher['days'] !== [])
                    <div class="space-y-3">
                        <p class="text-sm font-bold text-gray-950 dark:text-white">These {{ $teacher['day_count'] }} days</p>
                        @foreach ($teacher['days'] as $day)
                            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900" wire:key="teacher-day-{{ $teacher['user_id'] }}-{{ $day['sort'] }}">
                                <div class="flex items-center justify-between gap-3 px-4 py-3">
                                    <div>
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $day['date'] }}</p>
                                        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $day['subjects_given'] }} of {{ $day['subjects_expected'] }} {{ $day['subjects_expected'] === 1 ? 'subject' : 'subjects' }}</p>
                                    </div>
                                    <p class="text-lg font-bold text-gray-950 dark:text-white">{{ $day['percent'] }}%</p>
                                </div>
                                <div class="h-1 bg-gray-100 dark:bg-white/10">
                                    <div
                                        class="h-full {{ $day['percent'] >= 80 ? 'bg-emerald-500' : ($day['percent'] >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}"
                                        style="width: {{ $day['percent'] }}%"
                                    ></div>
                                </div>
                                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                                    @foreach ($day['subjects'] as $subject)
                                        <li class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-sm">
                                            <p class="text-gray-900 dark:text-gray-100">
                                                <span class="text-gray-500">{{ $subject['class'] }}</span>
                                                · {{ $subject['label'] }}
                                            </p>
                                            <p @class([
                                                'text-xs font-semibold',
                                                'text-rose-700 dark:text-rose-300' => $subject['state'] === 'Missed' || ($subject['lowers'] ?? false),
                                                'text-amber-700 dark:text-amber-300' => $subject['state'] !== 'Missed' && ! ($subject['lowers'] ?? false) && filled($subject['note'] ?? null),
                                                'text-emerald-700 dark:text-emerald-300' => $subject['state'] !== 'Missed' && ! ($subject['lowers'] ?? false) && blank($subject['note'] ?? null),
                                            ])>
                                                @if ($subject['state'] === 'Missed')
                                                    Missed
                                                @elseif ($subject['counts_open'])
                                                    Done {{ $subject['done'] }} · Not done {{ $subject['not_done'] }} · Not marked {{ $subject['unmarked'] }}
                                                @else
                                                    {{ $subject['note'] ?? 'Given' }}
                                                @endif
                                            </p>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No teacher found.</p>
        @endforelse
    @else
        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm dark:border-gray-700 dark:bg-gray-900">
            @if ($report['teachers'] === [])
                <p class="px-4 py-8 text-sm text-gray-500 dark:text-gray-400">No teachers found.</p>
            @else
                <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                    @foreach ($report['teachers'] as $teacher)
                        <li wire:key="teacher-row-{{ $teacher['user_id'] }}">
                            <button
                                type="button"
                                wire:click="openTeacher({{ (int) $teacher['user_id'] }})"
                                class="flex w-full items-center gap-4 px-4 py-4 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-gray-100 text-xs font-bold text-gray-600 dark:bg-white/10 dark:text-gray-300">{{ $loop->iteration }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $teacher['name'] }}</span>
                                    <span class="mt-1 block truncate text-xs text-gray-500 dark:text-gray-400">
                                        {{ $teacher['classes'] !== '' ? $teacher['classes'] : 'No subject assigned' }}
                                    </span>
                                    <span class="mt-2 block h-1.5 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                        <span
                                            class="block h-full rounded-full {{ ($teacher['score'] ?? 0) >= 80 ? 'bg-emerald-500' : (($teacher['score'] ?? 0) >= 50 ? 'bg-amber-500' : 'bg-rose-500') }}"
                                            style="width: {{ $teacher['score'] === null ? 0 : $teacher['score'] }}%"
                                        ></span>
                                    </span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                        @if ($teacher['day_count'] > 0)
                                            {{ $teacher['day_count'] }} {{ $teacher['day_count'] === 1 ? 'day' : 'days' }}
                                            · {{ $teacher['subject_given'] }} of {{ $teacher['subject_expected'] }} subjects
                                            @if ($teacher['waiting'] > 0)
                                                · Waiting {{ $teacher['waiting'] }}
                                            @endif
                                        @else
                                            No subject assigned
                                        @endif
                                    </span>
                                </span>
                                <span class="shrink-0 text-right text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $teacher['score'] === null ? '—' : $teacher['score'].'%' }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>

<div class="space-y-4">
    <div class="flex flex-wrap items-end gap-3">
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            From
            <input
                type="date"
                wire:model.live="dateFrom"
                max="{{ $maxDate }}"
                class="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
            />
        </label>
        <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
            To
            <input
                type="date"
                wire:model.live="dateTo"
                max="{{ $maxDate }}"
                class="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
            />
        </label>
        <p class="pb-2 text-sm text-gray-500 dark:text-gray-400">{{ $report['period_label'] }}</p>
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
            <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900" wire:key="teacher-score-{{ $teacher['user_id'] }}">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p class="text-base font-bold text-gray-950 dark:text-white">{{ $teacher['name'] }}</p>
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $teacher['classes'] !== '' ? $teacher['classes'] : 'No subject assigned' }}</p>
                    </div>
                    <p class="text-3xl font-bold text-gray-950 dark:text-white">{{ $teacher['score'] === null ? '—' : $teacher['score'] }}</p>
                </div>
                @if (filled($teacher['note'] ?? null))
                    <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $teacher['note'] }}</p>
                @endif
                <div class="mt-3 flex flex-wrap gap-2">
                    @foreach ($teacher['parts'] as $part)
                        <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200">
                            <span class="font-semibold">{{ $part['label'] }}</span>
                            · {{ $part['summary'] }}
                        </p>
                    @endforeach
                </div>
                @if ($teacher['lines'] !== [])
                    <ul class="mt-4 space-y-2">
                        @foreach ($teacher['lines'] as $line)
                            <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg border border-gray-200 px-3 py-2 text-sm dark:border-gray-700">
                                <p class="text-gray-900 dark:text-gray-100">
                                    <span class="text-gray-500">{{ $line['date'] }}</span>
                                    · {{ $line['label'] }}
                                </p>
                                @if ($line['state'] === 'Missed')
                                    <p class="text-xs font-semibold text-rose-700 dark:text-rose-300">Missed</p>
                                @elseif ($line['counts_open'])
                                    <p class="text-xs text-gray-600 dark:text-gray-300">
                                        Done {{ $line['done'] }} · Not done {{ $line['not_done'] }} · Not marked {{ $line['unmarked'] }}
                                    </p>
                                @else
                                    <p class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $line['note'] ?? $line['state'] }}</p>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @empty
            <p class="text-sm text-gray-500 dark:text-gray-400">No teacher found.</p>
        @endforelse
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            @if ($report['teachers'] === [])
                <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">No teachers found.</p>
            @else
                <ul class="divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($report['teachers'] as $teacher)
                        <li wire:key="teacher-row-{{ $teacher['user_id'] }}">
                            <button
                                type="button"
                                wire:click="openTeacher({{ (int) $teacher['user_id'] }})"
                                class="flex w-full flex-wrap items-center justify-between gap-3 px-4 py-3 text-left hover:bg-gray-50 dark:hover:bg-white/5"
                            >
                                <span>
                                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">{{ $teacher['name'] }}</span>
                                    <span class="mt-1 block text-xs text-gray-500 dark:text-gray-400">
                                        {{ $teacher['classes'] !== '' ? $teacher['classes'] : 'No subject assigned' }}
                                        · Given {{ $teacher['given'] }}
                                        · Missed {{ $teacher['missed'] }}
                                        · Waiting {{ $teacher['waiting'] }}
                                    </span>
                                </span>
                                <span class="text-xl font-bold text-gray-950 dark:text-white">{{ $teacher['score'] === null ? '—' : $teacher['score'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    @endif
</div>

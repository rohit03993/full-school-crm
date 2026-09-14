<div class="mx-auto max-w-5xl space-y-4">
    <div class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $periodLabel }}</p>
                @if ($viewingOther)
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $subjectName }} — only their own actions</p>
                @endif
            </div>
            <div class="flex flex-wrap items-center gap-2">
                <div class="crm-seg" role="group" aria-label="Activity view">
                    <button
                        type="button"
                        wire:click="setView('counts')"
                        @class(['crm-seg__btn', 'crm-seg__btn--active' => $view === 'counts'])
                    >
                        Counts
                    </button>
                    <button
                        type="button"
                        wire:click="setView('timeline')"
                        @class(['crm-seg__btn', 'crm-seg__btn--active' => $view === 'timeline'])
                    >
                        Timeline
                    </button>
                </div>
                <div class="crm-seg" role="group" aria-label="Activity range">
                    @foreach ($ranges as $option)
                        <button
                            type="button"
                            wire:click="setRange('{{ $option->value }}')"
                            @class(['crm-seg__btn', 'crm-seg__btn--active' => $range === $option->value])
                        >
                            {{ $option->label() }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    @if ($view === 'timeline')
        @php
            $items = collect($timeline['items'] ?? []);
            $grouped = $items->groupBy('occurred_date');
        @endphp

        @if ($items->isEmpty())
            <p class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
                Nothing recorded in this range.
            </p>
        @else
            <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:ring-white/10">
                @foreach ($grouped as $date => $dayItems)
                    <p class="mb-2 mt-4 text-[11px] font-bold uppercase tracking-wider text-gray-500 first:mt-0 dark:text-gray-400">
                        {{ $dayItems->first()['occurred_date_label'] ?? $date }}
                    </p>
                    <ul class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($dayItems as $item)
                            <li class="flex items-start gap-3 py-3">
                                <p class="w-16 shrink-0 pt-0.5 text-right text-xs font-semibold tabular-nums text-gray-700 dark:text-gray-200">{{ $item['occurred_at_label'] }}</p>
                                <div class="min-w-0">
                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-400">{{ $item['category'] }}</p>
                                    <p class="text-sm font-medium text-gray-950 dark:text-white">{{ $item['title'] }}</p>
                                    @if (filled($item['summary'] ?? null))
                                        @if (filled($item['url'] ?? null))
                                            <a href="{{ $item['url'] }}" class="text-sm text-primary-600 hover:text-primary-500 dark:text-primary-400">{{ $item['summary'] }}</a>
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">{{ $item['summary'] }}</p>
                                        @endif
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @endforeach

                @if ($timeline['has_more'] ?? false)
                    <button
                        type="button"
                        wire:click="loadMoreTimeline"
                        class="mt-3 text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-400"
                    >
                        Load more
                    </button>
                @endif
            </div>
        @endif
    @elseif ($tiles === [])
        <p class="rounded-xl bg-white px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-950/[0.06] dark:bg-gray-900 dark:text-gray-400 dark:ring-white/10">
            No tracked actions for this login.
        </p>
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            @foreach ($tiles as $tile)
                <a
                    href="{{ $tile['url'] }}"
                    class="rounded-2xl bg-white p-4 shadow-sm ring-1 ring-gray-950/[0.06] transition hover:ring-primary-500/40 dark:bg-gray-900 dark:ring-white/10"
                >
                    <p class="text-2xl font-semibold tabular-nums text-gray-950 dark:text-white">{{ $tile['value'] }}</p>
                    <p class="mt-1 text-sm font-medium text-gray-950 dark:text-white">{{ $tile['label'] }}</p>
                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">{{ $tile['meta'] }}</p>
                </a>
            @endforeach
        </div>
    @endif
</div>

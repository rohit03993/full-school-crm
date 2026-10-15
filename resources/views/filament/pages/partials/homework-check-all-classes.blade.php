<div class="rounded-xl border border-gray-200 bg-white p-3 sm:p-4 dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <p class="text-base font-bold text-gray-950 dark:text-white">Every class</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Every class for {{ $dateLabel }}.</p>
            @if ($isToday)
                <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Today's homework can be ticked from tomorrow. Counts stay closed today.</p>
            @endif
        </div>
        <label class="block w-full text-sm font-medium text-gray-700 sm:w-auto dark:text-gray-200">
            Date
            <input
                type="date"
                wire:model.live="reportDate"
                max="{{ $maxDate }}"
                class="mt-1 block w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 sm:w-auto dark:border-gray-600 dark:bg-gray-950 dark:text-white"
            />
        </label>
    </div>

    @if ($classes === [])
        <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No active classes.</p>
    @else
        <div class="mt-4 space-y-2">
            @foreach ($classes as $class)
                <div class="rounded-xl border border-gray-200 dark:border-gray-700" wire:key="hw-check-class-{{ $class['batch_id'] }}">
                    @if ($class['lines'] === [])
                        <div class="flex items-center justify-between gap-3 px-3 py-3">
                            <p class="min-w-0 text-sm font-semibold text-gray-950 dark:text-white">{{ $class['label'] }}</p>
                            <p class="shrink-0 text-xs text-gray-500 dark:text-gray-400">No homework</p>
                        </div>
                    @else
                        <div class="px-3 py-3">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $class['label'] }}</p>
                            <ul class="mt-3 space-y-2">
                                @foreach ($class['lines'] as $line)
                                    <li class="rounded-xl border border-gray-200 bg-gray-50 p-3 dark:border-gray-700 dark:bg-white/5" wire:key="hw-check-line-{{ $line['assignment_id'] }}">
                                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $line['label'] }}</p>

                                        @if ($line['counts_open'])
                                            <p class="sr-only">Done {{ $line['done'] }} · Not done {{ $line['not_done'] }} · Not checked {{ $line['unmarked'] }}</p>
                                            <div class="mt-3 grid grid-cols-3 gap-2">
                                                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-2 py-2 text-center dark:border-emerald-500/20 dark:bg-emerald-500/10">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Done</p>
                                                    <p class="mt-0.5 text-lg font-bold leading-none text-emerald-900 dark:text-emerald-200">{{ $line['done'] }}</p>
                                                </div>
                                                <div class="rounded-lg border border-rose-200 bg-rose-50 px-2 py-2 text-center dark:border-rose-500/20 dark:bg-rose-500/10">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-rose-700 dark:text-rose-300">Not done</p>
                                                    <p class="mt-0.5 text-lg font-bold leading-none text-rose-900 dark:text-rose-200">{{ $line['not_done'] }}</p>
                                                </div>
                                                <div class="rounded-lg border border-gray-200 bg-white px-2 py-2 text-center dark:border-gray-600 dark:bg-gray-900">
                                                    <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">Not checked</p>
                                                    <p class="mt-0.5 text-lg font-bold leading-none text-gray-950 dark:text-white">{{ $line['unmarked'] }}</p>
                                                </div>
                                            </div>
                                            @if (filled($line['check_url']))
                                                <a
                                                    href="{{ $line['check_url'] }}"
                                                    class="mt-3 flex min-h-11 items-center justify-center rounded-lg bg-primary-600 px-3 text-sm font-semibold text-white hover:bg-primary-500"
                                                    wire:navigate
                                                >Open</a>
                                            @endif
                                        @else
                                            <p class="mt-2 text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $line['note'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

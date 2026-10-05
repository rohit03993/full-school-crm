<x-filament-widgets::widget>
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-base font-bold text-gray-950 dark:text-white">Homework check</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Every class for {{ $dateLabel }}.</p>
                @if ($isToday)
                    <p class="mt-1 text-sm text-amber-700 dark:text-amber-300">Today's homework can be ticked from tomorrow. Counts stay closed today.</p>
                @endif
            </div>
            <label class="block text-sm font-medium text-gray-700 dark:text-gray-200">
                Date
                <input
                    type="date"
                    wire:model.live="reportDate"
                    max="{{ $maxDate }}"
                    class="mt-1 block rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-950 dark:border-gray-600 dark:bg-gray-950 dark:text-white"
                />
            </label>
        </div>

        @if ($classes === [])
            <p class="mt-4 text-sm text-gray-500 dark:text-gray-400">No active classes.</p>
        @else
            <div class="mt-4 space-y-3">
                @foreach ($classes as $class)
                    <div class="rounded-xl border border-gray-200 px-3 py-3 dark:border-gray-700" wire:key="hw-check-class-{{ $class['batch_id'] }}">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $class['label'] }}</p>

                        @if ($class['lines'] === [])
                            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">No homework</p>
                        @else
                            <ul class="mt-2 space-y-2">
                                @foreach ($class['lines'] as $line)
                                    <li class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5" wire:key="hw-check-line-{{ $line['assignment_id'] }}">
                                        <p class="text-sm font-medium text-gray-900 dark:text-gray-100">{{ $line['label'] }}</p>
                                        @if ($line['counts_open'])
                                            <p class="text-xs text-gray-600 dark:text-gray-300">
                                                Done {{ $line['done'] }}
                                                · Not done {{ $line['not_done'] }}
                                                · Not marked {{ $line['unmarked'] }}
                                                @if (filled($line['check_url']))
                                                    · <a href="{{ $line['check_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400" wire:navigate>Open</a>
                                                @endif
                                            </p>
                                        @else
                                            <p class="text-xs font-medium text-amber-700 dark:text-amber-300">{{ $line['note'] }}</p>
                                        @endif
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

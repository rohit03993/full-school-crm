<div class="rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="flex flex-wrap items-end justify-between gap-3 border-b border-gray-100 px-3 py-3 sm:px-4 dark:border-gray-800">
        <div>
            <p class="text-base font-bold text-gray-950 dark:text-white">Every class</p>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Every class for {{ $dateLabel }}.</p>
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
        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">No active classes.</p>
    @else
        <div class="divide-y divide-gray-100 dark:divide-gray-800">
            @foreach ($classes as $class)
                <section wire:key="hw-check-class-{{ $class['batch_id'] }}">
                    @if ($class['lines'] === [])
                        <div class="flex items-center justify-between gap-3 px-3 py-2.5 sm:px-4">
                            <p class="min-w-0 truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $class['label'] }}</p>
                            <p class="shrink-0 text-xs text-gray-400 dark:text-gray-500">No homework</p>
                        </div>
                    @else
                        <div class="bg-gray-50 px-3 py-2 sm:px-4 dark:bg-white/5">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $class['label'] }}</p>
                        </div>
                        <ul class="divide-y divide-gray-100 dark:divide-gray-800">
                            @foreach ($class['lines'] as $line)
                                <li wire:key="hw-check-line-{{ $line['assignment_id'] }}">
                                    @if ($line['counts_open'] && filled($line['check_url']))
                                        <a href="{{ $line['check_url'] }}" class="block px-3 py-2.5 hover:bg-gray-50 sm:px-4 dark:hover:bg-white/5" wire:navigate>
                                            @include('filament.pages.partials.homework-check-class-line', ['line' => $line, 'linked' => true])
                                        </a>
                                    @else
                                        <div class="px-3 py-2.5 sm:px-4">
                                            @include('filament.pages.partials.homework-check-class-line', ['line' => $line, 'linked' => false])
                                        </div>
                                    @endif
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </section>
            @endforeach
        </div>
    @endif
</div>

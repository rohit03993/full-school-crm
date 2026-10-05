@if ($score)
    <div class="rounded-xl border border-gray-200 bg-white p-4 dark:border-gray-700 dark:bg-gray-900">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <p class="text-base font-bold text-gray-950 dark:text-white">Homework score</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $period }}</p>
            </div>
            <p class="text-3xl font-bold text-gray-950 dark:text-white">
                {{ $score['score'] === null ? '—' : $score['score'].'%' }}
            </p>
        </div>
        @if (filled($score['note'] ?? null))
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">{{ $score['note'] }}</p>
        @endif
        <div class="mt-3 flex flex-wrap gap-2">
            @foreach ($score['parts'] as $part)
                <p class="rounded-lg bg-gray-50 px-3 py-2 text-xs text-gray-700 dark:bg-white/5 dark:text-gray-200">
                    <span class="font-semibold">{{ $part['label'] }}</span>
                    · {{ $part['summary'] }}
                </p>
            @endforeach
        </div>
        @if (filled($reportUrl ?? null))
            <p class="mt-3">
                <a href="{{ $reportUrl }}" class="text-sm font-semibold text-primary-600 hover:underline dark:text-primary-400" wire:navigate>Open full report</a>
            </p>
        @endif
    </div>
@endif

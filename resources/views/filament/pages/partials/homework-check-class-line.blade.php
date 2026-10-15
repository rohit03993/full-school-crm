<div class="flex items-start justify-between gap-3">
    <div class="min-w-0">
        <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $line['label'] }}</p>
        @if (filled($line['topic'] ?? null))
            <p class="mt-0.5 truncate text-xs text-gray-500 dark:text-gray-400">{{ $line['topic'] }}</p>
        @endif
    </div>
    @if ($linked)
        <span class="mt-0.5 shrink-0 rounded-md border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-gray-800 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-100">Open</span>
    @elseif (! ($line['counts_open'] ?? false))
        <p class="shrink-0 text-xs font-semibold text-amber-700 dark:text-amber-300">{{ $line['note'] }}</p>
    @endif
</div>

@if ($line['counts_open'] ?? false)
    <p class="sr-only">Done {{ $line['done'] }} · Not done {{ $line['not_done'] }} · Not checked {{ $line['unmarked'] }}</p>
    <div class="mt-2 grid grid-cols-3 gap-1.5">
        <p @class([
            'rounded-md px-1.5 py-1 text-center text-[11px] leading-4',
            'bg-emerald-50 font-semibold text-emerald-800 dark:bg-emerald-500/10 dark:text-emerald-200' => $line['done'] > 0,
            'text-gray-400 dark:text-gray-500' => $line['done'] < 1,
        ])>
            <span class="text-sm font-bold">{{ $line['done'] }}</span> Done
        </p>
        <p @class([
            'rounded-md px-1.5 py-1 text-center text-[11px] leading-4',
            'bg-rose-50 font-semibold text-rose-800 dark:bg-rose-500/10 dark:text-rose-200' => $line['not_done'] > 0,
            'text-gray-400 dark:text-gray-500' => $line['not_done'] < 1,
        ])>
            <span class="text-sm font-bold">{{ $line['not_done'] }}</span> Not done
        </p>
        <p @class([
            'rounded-md px-1.5 py-1 text-center text-[11px] leading-4',
            'bg-amber-50 font-semibold text-amber-900 dark:bg-amber-500/10 dark:text-amber-100' => ($line['unmarked'] ?? 0) > 0,
            'text-gray-400 dark:text-gray-500' => ($line['unmarked'] ?? 0) < 1,
        ])>
            <span class="text-sm font-bold">{{ $line['unmarked'] }}</span> Not checked
        </p>
    </div>
@endif

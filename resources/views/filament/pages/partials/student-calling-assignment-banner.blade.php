@if (! empty($callingAssignment))
    <div @class([
        'mt-3 rounded-xl px-3 py-2.5 ring-1',
        'bg-primary-50 ring-primary-200 dark:bg-primary-500/10 dark:ring-primary-500/30' => $callingAssignment['is_mine'],
        'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => ! $callingAssignment['is_mine'],
    ])>
        <p class="text-[10px] font-bold uppercase tracking-wider text-primary-700 dark:text-primary-300">
            Assigned to call
        </p>
        <p class="mt-0.5 text-sm font-semibold text-gray-950 dark:text-white">
            @if ($callingAssignment['is_mine'])
                Assigned to you
            @else
                {{ $callingAssignment['staff_name'] }}
            @endif
            <span class="font-normal text-gray-500 dark:text-gray-400">
                · {{ $callingAssignment['assigned_at']?->format('d M Y') }}
            </span>
        </p>
    </div>
@endif

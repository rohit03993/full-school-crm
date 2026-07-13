@php
    $summaries = $summaries ?? [];
    $selectedDate = $selectedDate ?? null;
@endphp

<div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
    <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-5">
        <h3 class="text-sm font-bold text-gray-950 dark:text-white">Marked dates</h3>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Tap a row to open that day</p>
    </div>

    @if ($summaries === [])
        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
            No attendance marked for this batch yet.
        </p>
    @else
        <x-crm.responsive-table>
            <table class="w-full min-w-[24rem] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium text-center">IN</th>
                        <th class="px-4 py-2 font-medium text-center">OUT</th>
                        <th class="px-4 py-2 font-medium text-center">Total</th>
                        <th class="px-4 py-2 font-medium"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($summaries as $row)
                        <tr
                            @class([
                                'hover:bg-gray-50 dark:hover:bg-white/5',
                                'bg-primary-50/60 dark:bg-primary-500/10' => $selectedDate === $row['date'],
                            ])
                        >
                            <td class="crm-responsive-table__title px-4 py-2.5 font-medium text-gray-900 dark:text-white" data-label="">
                                {{ $row['label'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center font-semibold text-emerald-600 dark:text-emerald-400" data-label="IN">
                                {{ $row['checked_in'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center font-semibold text-rose-600 dark:text-rose-400" data-label="OUT">
                                {{ $row['checked_out'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300" data-label="Total">
                                {{ $row['total'] }}
                            </td>
                            <td class="crm-responsive-table__actions px-4 py-2.5 text-right" data-label="">
                                <button
                                    type="button"
                                    wire:click="openMarkedDate(@js($row['date']))"
                                    class="inline-flex min-h-10 w-full items-center justify-center rounded-lg bg-primary-50 px-3 py-2 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                                >
                                    Open day
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </x-crm.responsive-table>
    @endif
</div>

@php
    $summaries = $summaries ?? [];
    $selectedDate = $selectedDate ?? null;
@endphp

<div class="rounded-xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
    <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Marked attendance dates</h3>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Past dates for this batch. Click a row to load and edit that day.
        </p>
    </div>

    @if ($summaries === [])
        <p class="px-4 py-6 text-sm text-gray-500 dark:text-gray-400">
            No attendance marked for this batch yet.
        </p>
    @else
        <div class="overflow-x-auto">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2 font-medium">Date</th>
                        <th class="px-4 py-2 font-medium text-center">Present</th>
                        <th class="px-4 py-2 font-medium text-center">Absent</th>
                        <th class="px-4 py-2 font-medium text-center">Leave</th>
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
                            <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-white">
                                {{ $row['label'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-success-600 dark:text-success-400">
                                {{ $row['present'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-danger-600 dark:text-danger-400">
                                {{ $row['absent'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-warning-600 dark:text-warning-400">
                                {{ $row['leave'] }}
                            </td>
                            <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300">
                                {{ $row['total'] }}
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <button
                                    type="button"
                                    wire:click="openMarkedDate(@js($row['date']))"
                                    class="text-xs font-medium text-primary-600 hover:text-primary-500 dark:text-primary-400"
                                >
                                    Open
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <div>
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Today by batch</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Students, attendance, and pending fees — {{ $overview['date_label'] }}
                </p>
            </div>
            <a
                href="{{ $attendanceUrl }}"
                wire:navigate
                class="inline-flex items-center rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500"
            >
                Mark attendance
            </a>
        </div>

        @if (($overview['rows'] ?? []) === [])
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">No active batches. Create batches under Academics.</p>
        @else
            <div class="max-h-[28rem] overflow-auto">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Batch</th>
                            <th class="px-4 py-2.5 text-center">Students</th>
                            <th class="px-4 py-2.5 text-center">Present</th>
                            <th class="px-4 py-2.5 text-center">Absent</th>
                            <th class="px-4 py-2.5 text-center">Not marked</th>
                            <th class="px-4 py-2.5 text-right">Pending fees</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($overview['rows'] as $row)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                                <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300">{{ $row['students'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    <span class="font-semibold text-emerald-700 dark:text-emerald-400">{{ $row['present_today'] }}</span>
                                </td>
                                <td class="px-4 py-2.5 text-center text-red-700 dark:text-red-400">{{ $row['absent_today'] }}</td>
                                <td class="px-4 py-2.5 text-center">
                                    @if ($row['not_marked_today'] > 0)
                                        <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                            {{ $row['not_marked_today'] }}
                                        </span>
                                    @else
                                        <span class="text-gray-400">0</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 text-right font-semibold text-amber-700 dark:text-amber-400">
                                    ₹{{ number_format((float) $row['pending_fees'], 0) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                        <tr>
                            <td class="px-4 py-2.5">All batches</td>
                            <td class="px-4 py-2.5 text-center">{{ $overview['totals']['students'] }}</td>
                            <td class="px-4 py-2.5 text-center text-emerald-700 dark:text-emerald-400">{{ $overview['totals']['present_today'] }}</td>
                            <td class="px-4 py-2.5 text-center text-red-700 dark:text-red-400">{{ $overview['totals']['absent_today'] }}</td>
                            <td class="px-4 py-2.5 text-center">{{ $overview['totals']['not_marked_today'] }}</td>
                            <td class="px-4 py-2.5 text-right text-amber-700 dark:text-amber-400">₹{{ number_format((float) $overview['totals']['pending_fees'], 0) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

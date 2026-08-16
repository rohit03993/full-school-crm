@php
    $totals = $overview['totals'];
    $coverage = $totals['students'] > 0
        ? (int) round(($totals['marked_today'] / $totals['students']) * 100)
        : 0;
@endphp

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <div class="min-w-0">
                <h3 class="text-base font-bold text-gray-950 dark:text-white">
                    {{ $isToday ? 'Today' : $overview['date_label'] }} by {{ strtolower($batchLabel) }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Students and attendance — {{ $overview['date_label'] }}
                </p>
            </div>
            @if ($attendanceUrl)
                <a
                    href="{{ $attendanceUrl }}"
                    wire:navigate
                    class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-3.5 py-2 text-sm font-semibold text-white hover:bg-primary-500 touch-manipulation"
                >
                    Mark attendance
                </a>
            @endif
        </div>

        @if (($overview['rows'] ?? []) === [])
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">
                No active {{ strtolower($batchLabel) }} matches these filters. Widen the session or clear the filters above.
            </p>
        @else
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                <div class="flex items-center justify-between text-xs font-semibold text-gray-600 dark:text-gray-400">
                    <span>Attendance marked</span>
                    <span>{{ $totals['marked_today'] }} of {{ $totals['students'] }} · {{ $coverage }}%</span>
                </div>
                <div class="mt-1.5 h-2 w-full overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                    <div
                        class="h-full rounded-full {{ $coverage >= 90 ? 'bg-emerald-500' : ($coverage > 0 ? 'bg-amber-500' : 'bg-gray-300') }}"
                        style="width: {{ max(2, min(100, $coverage)) }}%"
                    ></div>
                </div>
            </div>

            {{-- Mobile: one card per batch --}}
            <div class="space-y-2 p-3 lg:hidden">
                @foreach ($overview['rows'] as $row)
                    <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                        <div class="mt-2 grid grid-cols-3 gap-2 text-xs">
                            <div>
                                <p class="font-semibold uppercase tracking-wide text-gray-500">Students</p>
                                <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $row['students'] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-wide text-gray-500">Present</p>
                                <p class="mt-0.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $row['present_today'] }}</p>
                            </div>
                            <div>
                                <p class="font-semibold uppercase tracking-wide text-gray-500">Absent</p>
                                <p class="mt-0.5 text-sm font-semibold text-red-700 dark:text-red-400">{{ $row['absent_today'] }}</p>
                            </div>
                        </div>
                    </div>
                @endforeach

                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">All {{ strtolower($batchLabel) }}</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-gray-950 dark:text-white">
                        <span>{{ $totals['students'] }} students</span>
                        <span class="text-emerald-700 dark:text-emerald-400">{{ $totals['present_today'] }} present</span>
                        <span class="text-red-700 dark:text-red-400">{{ $totals['absent_today'] }} absent</span>
                    </div>
                </div>
            </div>

            {{-- Desktop table --}}
            <div class="hidden max-h-[28rem] overflow-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">{{ $batchLabel }}</th>
                            <th class="px-4 py-2.5 text-center">Students</th>
                            <th class="px-4 py-2.5 text-center">Present</th>
                            <th class="px-4 py-2.5 text-center">Absent</th>
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
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                        <tr>
                            <td class="px-4 py-2.5">All {{ strtolower($batchLabel) }}</td>
                            <td class="px-4 py-2.5 text-center">{{ $totals['students'] }}</td>
                            <td class="px-4 py-2.5 text-center text-emerald-700 dark:text-emerald-400">{{ $totals['present_today'] }}</td>
                            <td class="px-4 py-2.5 text-center text-red-700 dark:text-red-400">{{ $totals['absent_today'] }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

<x-filament-widgets::widget>
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <div>
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Today by batch</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    @if ($showAttendance && $showFees)
                        Students, attendance, and pending fees — {{ $overview['date_label'] }}
                    @elseif ($showAttendance)
                        Students and attendance — {{ $overview['date_label'] }}
                    @else
                        Students and pending fees — {{ $overview['date_label'] }}
                    @endif
                </p>
            </div>
            @if ($showAttendance)
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
            <p class="px-4 py-8 text-center text-sm text-gray-500 sm:px-6 dark:text-gray-400">No active batches. Create batches under Academics.</p>
        @else
            {{-- Mobile: one card per batch — no sideways scroll --}}
            <div class="space-y-2 p-3 lg:hidden">
                @foreach ($overview['rows'] as $row)
                    <div class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                        <p class="text-sm font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                        <div class="mt-2 grid grid-cols-2 gap-2 text-xs">
                            <div>
                                <p class="font-semibold uppercase tracking-wide text-gray-500">Students</p>
                                <p class="mt-0.5 text-sm font-semibold text-gray-900 dark:text-gray-100">{{ $row['students'] }}</p>
                            </div>
                            @if ($showAttendance)
                                <div>
                                    <p class="font-semibold uppercase tracking-wide text-gray-500">Present</p>
                                    <p class="mt-0.5 text-sm font-semibold text-emerald-700 dark:text-emerald-400">{{ $row['present_today'] }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold uppercase tracking-wide text-gray-500">Absent</p>
                                    <p class="mt-0.5 text-sm font-semibold text-red-700 dark:text-red-400">{{ $row['absent_today'] }}</p>
                                </div>
                                <div>
                                    <p class="font-semibold uppercase tracking-wide text-gray-500">Not marked</p>
                                    <p class="mt-0.5 text-sm font-semibold">
                                        @if ($row['not_marked_today'] > 0)
                                            <span class="rounded-full bg-amber-50 px-2 py-0.5 text-amber-800 dark:bg-amber-500/10 dark:text-amber-300">
                                                {{ $row['not_marked_today'] }}
                                            </span>
                                        @else
                                            <span class="text-gray-400">0</span>
                                        @endif
                                    </p>
                                </div>
                            @endif
                            @if ($showFees)
                                <div class="col-span-2">
                                    <p class="font-semibold uppercase tracking-wide text-gray-500">Pending fees</p>
                                    <p class="mt-0.5 text-sm font-semibold text-amber-700 dark:text-amber-400">
                                        ₹{{ number_format((float) $row['pending_fees'], 0) }}
                                    </p>
                                </div>
                            @endif
                        </div>
                    </div>
                @endforeach

                <div class="rounded-xl border border-gray-200 bg-white p-3 dark:border-white/10 dark:bg-gray-900">
                    <p class="text-xs font-bold uppercase tracking-wide text-gray-500">All batches</p>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm font-semibold text-gray-950 dark:text-white">
                        <span>{{ $overview['totals']['students'] }} students</span>
                        @if ($showAttendance)
                            <span class="text-emerald-700 dark:text-emerald-400">{{ $overview['totals']['present_today'] }} present</span>
                            <span class="text-red-700 dark:text-red-400">{{ $overview['totals']['absent_today'] }} absent</span>
                            <span>{{ $overview['totals']['not_marked_today'] }} open</span>
                        @endif
                        @if ($showFees)
                            <span class="text-amber-700 dark:text-amber-400">₹{{ number_format((float) $overview['totals']['pending_fees'], 0) }} pending</span>
                        @endif
                    </div>
                </div>
            </div>

            {{-- Desktop table --}}
            <div class="hidden max-h-[28rem] overflow-auto lg:block">
                <table class="w-full text-left text-sm">
                    <thead class="sticky top-0 z-10 bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Batch</th>
                            <th class="px-4 py-2.5 text-center">Students</th>
                            @if ($showAttendance)
                                <th class="px-4 py-2.5 text-center">Present</th>
                                <th class="px-4 py-2.5 text-center">Absent</th>
                                <th class="px-4 py-2.5 text-center">Not marked</th>
                            @endif
                            @if ($showFees)
                                <th class="px-4 py-2.5 text-right">Pending fees</th>
                            @endif
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($overview['rows'] as $row)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">{{ $row['label'] }}</td>
                                <td class="px-4 py-2.5 text-center text-gray-700 dark:text-gray-300">{{ $row['students'] }}</td>
                                @if ($showAttendance)
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
                                @endif
                                @if ($showFees)
                                    <td class="px-4 py-2.5 text-right font-semibold text-amber-700 dark:text-amber-400">
                                        ₹{{ number_format((float) $row['pending_fees'], 0) }}
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-gray-50 font-semibold text-gray-950 dark:bg-white/5 dark:text-white">
                        <tr>
                            <td class="px-4 py-2.5">All batches</td>
                            <td class="px-4 py-2.5 text-center">{{ $overview['totals']['students'] }}</td>
                            @if ($showAttendance)
                                <td class="px-4 py-2.5 text-center text-emerald-700 dark:text-emerald-400">{{ $overview['totals']['present_today'] }}</td>
                                <td class="px-4 py-2.5 text-center text-red-700 dark:text-red-400">{{ $overview['totals']['absent_today'] }}</td>
                                <td class="px-4 py-2.5 text-center">{{ $overview['totals']['not_marked_today'] }}</td>
                            @endif
                            @if ($showFees)
                                <td class="px-4 py-2.5 text-right text-amber-700 dark:text-amber-400">₹{{ number_format((float) $overview['totals']['pending_fees'], 0) }}</td>
                            @endif
                        </tr>
                    </tfoot>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>

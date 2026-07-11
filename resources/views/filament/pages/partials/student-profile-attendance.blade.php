@if (! $attendanceTabLoaded)
    <div wire:init="loadAttendanceTab" class="flex items-center justify-center py-12 text-sm text-gray-500 dark:text-gray-400">
        Loading attendance…
    </div>
@elseif (! $activeBatch)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        No active batch assigned. Use <span class="font-semibold">Assign Batch</span> from the profile actions.
    </div>
@else
    @php
        $attendanceMonth = $attendanceMonth ?: now()->format('Y-m');
        $attendancePage = $attendancePage ?? 1;
        $attendanceTotal = $attendanceTotal ?? 0;
        $attendanceLastPage = $attendanceLastPage ?? 1;
        $attendancePerPage = $attendancePerPage ?? 15;
    @endphp
    <div class="space-y-4">
        <div class="rounded-xl bg-primary-500/5 px-4 py-3 ring-1 ring-primary-500/15 dark:bg-primary-500/10">
            <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                <div>
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active batch</p>
                    <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">{{ $activeBatch->name }}</p>
                    @if ($attendancePercentage !== null && $attendanceSummary)
                        <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                            Attendance:
                            <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ $attendancePercentage }}%</span>
                            <span class="text-gray-400">·</span>
                            {{ $attendanceSummary['credited_days'] }}/{{ $attendanceSummary['expected_days'] }} working days
                            <span class="text-gray-400">({{ $attendanceSummary['period_label'] }})</span>
                        </p>
                        <p class="mt-0.5 text-xs text-gray-500">
                            Present {{ $attendanceSummary['present_days'] }}
                            · Leave {{ $attendanceSummary['leave_days'] }}
                            · Absent {{ $attendanceSummary['absent_days'] ?? max(0, $attendanceSummary['expected_days'] - $attendanceSummary['credited_days']) }}
                        </p>
                    @else
                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No working days in this month yet.</p>
                    @endif
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <label class="sr-only" for="attendance-month">Month</label>
                    <input
                        id="attendance-month"
                        type="month"
                        wire:model.live="attendanceMonth"
                        class="rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    />
                    <button
                        type="button"
                        wire:click="downloadAttendanceMonthPdf"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-white px-3 py-2 text-xs font-semibold text-gray-800 ring-1 ring-gray-200 hover:bg-gray-50 dark:bg-white/10 dark:text-gray-100 dark:ring-white/10"
                    >
                        Print / PDF
                    </button>
                </div>
            </div>
        </div>

        <div class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">How to read this</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li><strong>%</strong> — Selected month: Present(+Leave) ÷ working days (Sundays excluded)</li>
                <li><strong>Visits</strong> — Each IN→OUT pair with source under the punch (machine or manually marked)</li>
                <li>Use month filter + Print/PDF for parent or file copies. Full class reports: Reports menu.</li>
            </ul>
        </div>

        @if ($attendanceRecords->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">No attendance rows in this month.</p>
        @else
            <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Visits · source per punch</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($attendanceRecords as $record)
                            @php
                                $roll = $student?->activeEnrollment?->enrollment_number;
                                $dayRow = filled($roll)
                                    ? app(\App\Services\Punch\LivePunchDashboardService::class)->studentDayRow(
                                        (string) $roll,
                                        $record->attendance_date->toDateString(),
                                        $student,
                                    )
                                    : null;
                                $pairs = $dayRow['pairs'] ?? [];
                                $lastPair = $pairs !== [] ? $pairs[array_key_last($pairs)] : null;
                                $visit = $lastPair
                                    ? (filled($lastPair['out'] ?? null) ? 'Checked out' : 'Inside')
                                    : \App\Support\AttendanceSourceLabel::visitState($record->checked_in_at, $record->checked_out_at);
                            @endphp
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white align-top">
                                    {{ $record->attendance_date->format('d M Y') }}
                                    @if (count($pairs) > 1)
                                        <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-gray-400">
                                            {{ count($pairs) }} visits
                                        </p>
                                    @endif
                                </td>
                                <td class="px-4 py-2.5 align-top">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $record->status === \App\Enums\AttendanceStatus::Present,
                                            'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => $record->status === \App\Enums\AttendanceStatus::Absent,
                                            'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300' => $record->status === \App\Enums\AttendanceStatus::Leave,
                                        ])>
                                            {{ $record->status->label() }}
                                        </span>
                                        @if ($visit && $record->status === \App\Enums\AttendanceStatus::Present)
                                            <span @class([
                                                'inline-flex rounded-full px-2 py-0.5 text-[10px] font-bold uppercase',
                                                'bg-emerald-500/15 text-emerald-700 dark:text-emerald-300' => $visit === 'Inside',
                                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $visit === 'Checked out',
                                            ])>{{ $visit }}</span>
                                        @endif
                                    </div>
                                </td>
                                <td class="px-4 py-2.5 align-top">
                                    @if ($pairs !== [])
                                        <div class="space-y-2.5">
                                            @foreach ($pairs as $index => $pair)
                                                <div class="rounded-lg bg-gray-50 px-2.5 py-2 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10">
                                                    <div class="mb-1.5 flex items-center justify-between gap-2">
                                                        <span class="text-[10px] font-bold uppercase tracking-wide text-gray-400">
                                                            Visit {{ $index + 1 }}
                                                        </span>
                                                        @if (filled($pair['duration_label'] ?? null))
                                                            <span class="text-[10px] text-gray-400">{{ $pair['duration_label'] }}</span>
                                                        @endif
                                                    </div>
                                                    <div class="grid grid-cols-2 gap-3">
                                                        <div class="min-w-0">
                                                            <p class="text-[9px] font-semibold uppercase text-emerald-600 dark:text-emerald-400">In</p>
                                                            <p class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-300">{{ $pair['in'] ?? '—' }}</p>
                                                            @include('filament.pages.partials.punch-source-chip', [
                                                                'isManual' => ! empty($pair['is_manual_in']),
                                                                'device' => $pair['device_in'] ?? null,
                                                                'staffName' => $pair['marked_by_in'] ?? null,
                                                            ])
                                                        </div>
                                                        <div class="min-w-0">
                                                            <p class="text-[9px] font-semibold uppercase text-rose-600 dark:text-rose-400">Out</p>
                                                            @if (filled($pair['out'] ?? null) || ! empty($pair['is_auto_out']))
                                                                <p class="font-mono text-xs font-bold text-rose-700 dark:text-rose-300">{{ $pair['out'] ?? '—' }}</p>
                                                                @include('filament.pages.partials.punch-source-chip', [
                                                                    'isManual' => ! empty($pair['is_manual_out']),
                                                                    'isAuto' => ! empty($pair['is_auto_out']),
                                                                    'device' => $pair['device_out'] ?? null,
                                                                    'staffName' => $pair['marked_by_out'] ?? null,
                                                                ])
                                                            @else
                                                                <p class="text-[11px] font-bold uppercase text-emerald-700 dark:text-emerald-300">Inside</p>
                                                            @endif
                                                        </div>
                                                    </div>
                                                </div>
                                            @endforeach
                                        </div>
                                    @else
                                        <div class="flex flex-wrap gap-x-4 gap-y-1 font-mono text-xs">
                                            <span class="text-emerald-700 dark:text-emerald-300">
                                                IN {{ $record->checked_in_at?->format('H:i') ?? '—' }}
                                            </span>
                                            <span class="text-rose-700 dark:text-rose-300">
                                                OUT {{ $record->checked_out_at?->format('H:i') ?? '—' }}
                                            </span>
                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-gray-500">
                <p>
                    Showing
                    {{ min($attendanceTotal, (($attendancePage - 1) * $attendancePerPage) + 1) }}–{{ min($attendanceTotal, $attendancePage * $attendancePerPage) }}
                    of {{ $attendanceTotal }}
                    · {{ $attendancePerPage }} per page
                </p>
                <div class="flex items-center gap-2">
                    <button
                        type="button"
                        wire:click="previousAttendancePage"
                        @disabled($attendancePage <= 1)
                        class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40 dark:ring-white/10"
                    >Prev</button>
                    <span class="tabular-nums">{{ $attendancePage }} / {{ $attendanceLastPage }}</span>
                    <button
                        type="button"
                        wire:click="nextAttendancePage"
                        @disabled($attendancePage >= $attendanceLastPage)
                        class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40 dark:ring-white/10"
                    >Next</button>
                </div>
            </div>
        @endif
    </div>
@endif

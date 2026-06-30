@if (! $attendanceTabLoaded)
    <div wire:init="loadAttendanceTab" class="flex items-center justify-center py-12 text-sm text-gray-500 dark:text-gray-400">
        Loading attendance…
    </div>
@elseif (! $activeBatch)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        No active batch assigned. Use <span class="font-semibold">Assign Batch</span> from the profile actions.
    </div>
@else
    <div class="space-y-4">
        <div class="rounded-xl bg-primary-500/5 px-4 py-3 ring-1 ring-primary-500/15 dark:bg-primary-500/10">
            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Active batch</p>
            <p class="mt-0.5 text-base font-bold text-gray-950 dark:text-white">{{ $activeBatch->name }}</p>
            @if ($attendancePercentage !== null)
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    Attendance: <span class="font-bold text-emerald-700 dark:text-emerald-400">{{ $attendancePercentage }}%</span>
                    <span class="text-gray-400">·</span> {{ $attendanceRecords->count() }} day(s) recorded
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No attendance recorded yet for this batch.</p>
            @endif
        </div>

        <div class="rounded-xl bg-gray-50 px-4 py-3 text-xs text-gray-600 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-300 dark:ring-white/10">
            <p class="font-semibold text-gray-950 dark:text-white">What appears here</p>
            <ul class="mt-2 list-inside list-disc space-y-1">
                <li><strong>Status</strong> — Present (from IN), Absent, or Leave</li>
                <li><strong>Check-in / Check-out</strong> — times from biometric or manual IN/OUT (same on Attendance screen)</li>
                <li><strong>Source</strong> — Biometric device, Manual IN/OUT, or roll-call only (A/L)</li>
            </ul>
        </div>

        @if ($attendanceRecords->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Records appear after a punch IN, manual IN, or absent/leave mark.</p>
        @else
            <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Status</th>
                            <th class="px-4 py-2.5">Check-in</th>
                            <th class="px-4 py-2.5">Check-out</th>
                            <th class="px-4 py-2.5">Source</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($attendanceRecords as $record)
                            @php
                                $visit = \App\Support\AttendanceSourceLabel::visitState($record->checked_in_at, $record->checked_out_at);
                            @endphp
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                                    {{ $record->attendance_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-2.5">
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
                                <td class="px-4 py-2.5 font-mono text-xs text-emerald-700 dark:text-emerald-300">
                                    {{ $record->checked_in_at?->format('H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 font-mono text-xs text-rose-700 dark:text-rose-300">
                                    {{ $record->checked_out_at?->format('H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-2.5 text-xs text-gray-600 dark:text-gray-300">
                                    {{ \App\Support\AttendanceSourceLabel::for($record->punch_source) }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

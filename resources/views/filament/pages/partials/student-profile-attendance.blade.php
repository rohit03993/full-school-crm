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
                    <span class="text-gray-400">·</span> {{ $attendanceRecords->count() }} class day(s) recorded
                </p>
            @else
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">No daily attendance marked yet for this batch.</p>
            @endif
        </div>

        @if ($attendanceRecords->isEmpty())
            <p class="text-sm text-gray-500 dark:text-gray-400">Daily class attendance appears here after staff marks batch attendance.</p>
        @else
            <div class="overflow-hidden rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
                <table class="w-full text-left text-sm">
                    <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2.5">Date</th>
                            <th class="px-4 py-2.5">Status</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($attendanceRecords as $record)
                            <tr class="bg-white dark:bg-gray-900">
                                <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                                    {{ $record->attendance_date->format('d M Y') }}
                                </td>
                                <td class="px-4 py-2.5">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $record->status === \App\Enums\AttendanceStatus::Present,
                                        'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-300' => $record->status === \App\Enums\AttendanceStatus::Absent,
                                        'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-300' => $record->status === \App\Enums\AttendanceStatus::Leave,
                                    ])>
                                        {{ $record->status->label() }} ({{ $record->status->code() }})
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
@endif

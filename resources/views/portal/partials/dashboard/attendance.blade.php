@php
    $summary = $classAttendanceSummary ?? null;
    $hasClassSummary = is_array($summary) && ($summary['expected_days'] ?? 0) > 0;
    $hasWorkshops = ($sessionAttendanceRecords ?? collect())->isNotEmpty();
@endphp

<section class="portal-card overflow-hidden">
    <div class="border-b border-navy-100 px-4 py-3.5 sm:px-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h2 class="font-display text-lg font-bold text-navy-900">Attendance</h2>
                <p class="mt-0.5 text-sm text-navy-500">
                    {{ $summary['period_label'] ?? 'This month' }} · {{ $student->name }}
                </p>
            </div>
            <form method="GET" action="{{ route('portal.dashboard') }}" class="flex items-center gap-2">
                <input type="hidden" name="tab" value="attendance">
                <label for="attendance_month" class="sr-only">Month</label>
                <input
                    id="attendance_month"
                    type="month"
                    name="attendance_month"
                    value="{{ $attendanceMonth ?? now()->format('Y-m') }}"
                    max="{{ now()->format('Y-m') }}"
                    class="rounded-xl border border-navy-200 bg-white px-3 py-2 text-sm font-semibold text-navy-800 shadow-sm"
                    onchange="this.form.submit()"
                >
            </form>
        </div>
    </div>

    <div class="space-y-4 p-4 sm:p-5">
        @if ($hasClassSummary)
            <div class="flex items-center gap-4 rounded-xl bg-emerald-50 p-4 ring-1 ring-emerald-100">
                <div class="flex h-14 w-14 shrink-0 items-center justify-center rounded-2xl bg-emerald-500 text-lg font-bold text-white">
                    {{ (int) round((float) $summary['percentage']) }}%
                </div>
                <div>
                    <p class="text-xs font-bold uppercase tracking-wider text-emerald-700">Class attendance</p>
                    <p class="mt-0.5 text-sm text-emerald-800">
                        {{ (int) $summary['present_days'] }} present
                        · {{ (int) $summary['absent_days'] }} absent
                        @if ((int) ($summary['leave_days'] ?? 0) > 0)
                            · {{ (int) $summary['leave_days'] }} leave
                        @endif
                        of {{ (int) $summary['expected_days'] }} school days
                    </p>
                </div>
            </div>

            <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                <div class="rounded-xl bg-navy-50 px-3 py-3 text-center">
                    <p class="text-lg font-bold text-navy-900">{{ (int) $summary['present_days'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-navy-500">Present</p>
                </div>
                <div class="rounded-xl bg-navy-50 px-3 py-3 text-center">
                    <p class="text-lg font-bold text-navy-900">{{ (int) $summary['absent_days'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-navy-500">Absent</p>
                </div>
                <div class="rounded-xl bg-navy-50 px-3 py-3 text-center">
                    <p class="text-lg font-bold text-navy-900">{{ (int) ($summary['leave_days'] ?? 0) }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-navy-500">Leave</p>
                </div>
                <div class="rounded-xl bg-navy-50 px-3 py-3 text-center">
                    <p class="text-lg font-bold text-navy-900">{{ (int) $summary['expected_days'] }}</p>
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-navy-500">Expected</p>
                </div>
            </div>
        @elseif ($enrollment)
            <p class="rounded-xl bg-navy-50 px-4 py-6 text-center text-sm text-navy-500">
                No class attendance recorded for this month yet.
            </p>
        @else
            <p class="rounded-xl bg-navy-50 px-4 py-6 text-center text-sm text-navy-500">
                Attendance appears here after enrollment in a class.
            </p>
        @endif

        @if ($hasWorkshops)
            <div>
                <p class="text-xs font-bold uppercase tracking-wider text-navy-500">Workshops &amp; events</p>
                <ul class="mt-2 divide-y divide-navy-100 overflow-hidden rounded-xl border border-navy-100">
                    @foreach ($sessionAttendanceRecords as $record)
                        @php $session = $record->attendable; @endphp
                        <li class="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-semibold text-navy-900">{{ $session?->title ?? 'Session' }}</p>
                                <p class="text-sm text-navy-500">
                                    {{ $session?->activityType?->name ?? 'Activity' }}
                                    · {{ $session?->session_date?->format('d M Y') ?? '—' }}
                                </p>
                            </div>
                            <span @class([
                                'inline-flex w-fit rounded-full px-3 py-1 text-xs font-bold',
                                'bg-emerald-100 text-emerald-800' => $record->is_present,
                                'bg-rose-100 text-rose-800' => ! $record->is_present,
                            ])>
                                {{ $record->is_present ? 'Present' : 'Absent' }}
                            </span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </div>
</section>

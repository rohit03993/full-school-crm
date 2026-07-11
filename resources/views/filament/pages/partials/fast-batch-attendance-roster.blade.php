@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    /** @var array<int, array{status: string, checked_in_at: ?string, checked_out_at: ?string, is_inside: bool, punch_source?: ?string}> $attendanceSnapshot */

    $rows = $roster->map(function ($row) use ($attendanceSnapshot): array {
        $student = $row->student;
        $snapshot = $attendanceSnapshot[$student->id] ?? null;
        $checkedIn = $snapshot['checked_in_at'] ?? null;
        $checkedOut = $snapshot['checked_out_at'] ?? null;
        $isInside = (bool) ($snapshot['is_inside'] ?? false);
        $source = $snapshot['punch_source'] ?? null;
        $attendance = $checkedIn !== null ? 'present' : 'absent';
        $track = $checkedOut !== null ? 'out' : ($isInside ? 'in' : 'pending');
        $roll = $student->activeEnrollment?->enrollment_number;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'mobile' => $student->mobile,
            'roll' => $roll,
            'checked_in' => $checkedIn,
            'checked_out' => $checkedOut,
            'is_inside' => $isInside,
            'attendance' => $attendance,
            'track' => $track,
            'source' => $source,
            'source_label' => \App\Support\AttendanceSourceLabel::for($source),
            'sort' => match ($attendance) {
                'absent' => 0,
                default => $track === 'in' ? 1 : 2,
            },
        ];
    })
        ->sortBy([
            ['sort', 'asc'],
            ['name', 'asc'],
        ])
        ->values();

    $enrolled = $rows->count();
    $present = $rows->where('attendance', 'present')->count();
    $absent = $rows->where('attendance', 'absent')->count();
    $inside = $rows->where('track', 'in')->count();
    $checkedOutCount = $rows->where('track', 'out')->count();
    $percentage = $enrolled > 0 ? round(($present / $enrolled) * 100, 1) : 0;
@endphp

<div
    wire:key="manual-attendance-roster"
    x-data="{
        q: '',
        filter: 'all',
        matches(row) {
            if (this.filter === 'present' && row.attendance !== 'present') return false;
            if (this.filter === 'absent' && row.attendance !== 'absent') return false;
            if (this.filter === 'in' && row.track !== 'in') return false;
            if (this.filter === 'out' && row.track !== 'out') return false;
            const needle = this.q.trim().toLowerCase();
            if (!needle) return true;
            return (row.name || '').toLowerCase().includes(needle)
                || (row.roll || '').toLowerCase().includes(needle)
                || (row.mobile || '').toLowerCase().includes(needle);
        }
    }"
    class="space-y-3"
>
    <div class="fi-section sticky top-0 z-10 space-y-3 rounded-2xl bg-white/95 px-3 py-3 shadow-sm ring-1 ring-gray-950/5 backdrop-blur dark:bg-gray-900/95 dark:ring-white/10 sm:px-4">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-gray-950 dark:text-white">
                    Class attendance today
                </p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    Present = IN at least once today. IN/OUT times show if they are still inside. Tap Absent for the full absent list.
                </p>
            </div>
            @if ($absent > 0)
                <button
                    type="button"
                    wire:click="checkInAllStudents"
                    wire:loading.attr="disabled"
                    wire:target="checkInAllStudents"
                    class="inline-flex shrink-0 items-center justify-center gap-2 rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-500 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="checkInAllStudents">Check in remaining ({{ $absent }})</span>
                    <span wire:loading wire:target="checkInAllStudents">Checking in…</span>
                </button>
            @endif
        </div>

        <div class="grid grid-cols-2 gap-2 lg:grid-cols-4">
            <button
                type="button"
                x-on:click="filter = 'all'"
                x-bind:class="filter === 'all'
                    ? 'ring-2 ring-primary-500 bg-primary-50 dark:bg-primary-500/10'
                    : 'ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5'"
                class="rounded-xl px-3 py-2.5 text-left transition"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Enrolled</p>
                <p class="text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $enrolled }}</p>
                <p class="mt-0.5 text-[10px] text-gray-400">Whole batch</p>
            </button>

            <button
                type="button"
                x-on:click="filter = 'present'"
                x-bind:class="filter === 'present'
                    ? 'ring-2 ring-emerald-500 bg-emerald-50 dark:bg-emerald-500/10'
                    : 'ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5'"
                class="rounded-xl px-3 py-2.5 text-left transition"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Present</p>
                <p class="text-xl font-bold tabular-nums text-emerald-700 dark:text-emerald-300">{{ $present }}</p>
                <p class="mt-0.5 text-[10px] text-gray-400">Came today</p>
            </button>

            <button
                type="button"
                x-on:click="filter = 'absent'"
                x-bind:class="filter === 'absent'
                    ? 'ring-2 ring-rose-500 bg-rose-50 dark:bg-rose-500/10'
                    : 'ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5'"
                class="rounded-xl px-3 py-2.5 text-left transition"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Absent</p>
                <p class="text-xl font-bold tabular-nums text-rose-700 dark:text-rose-300">{{ $absent }}</p>
                <p class="mt-0.5 text-[10px] text-rose-600/80 dark:text-rose-300/80">Tap to view list →</p>
            </button>

            <div class="rounded-xl px-3 py-2.5 ring-1 ring-gray-200 dark:ring-white/10">
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Class %</p>
                <p class="text-xl font-bold tabular-nums text-primary-700 dark:text-primary-300">{{ $percentage }}%</p>
                <p class="mt-0.5 text-[10px] text-gray-400">{{ $present }}/{{ $enrolled }} present</p>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <span class="text-[10px] font-semibold uppercase tracking-wide text-gray-400">Live track</span>
            <button
                type="button"
                x-on:click="filter = 'in'"
                x-bind:class="filter === 'in' ? 'bg-emerald-600 text-white' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                class="rounded-full px-2.5 py-1 text-[11px] font-bold transition"
            >
                Inside now {{ $inside }}
            </button>
            <button
                type="button"
                x-on:click="filter = 'out'"
                x-bind:class="filter === 'out' ? 'bg-gray-700 text-white dark:bg-gray-200 dark:text-gray-900' : 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-200'"
                class="rounded-full px-2.5 py-1 text-[11px] font-bold transition"
            >
                Checked out {{ $checkedOutCount }}
            </button>
            <button
                type="button"
                x-show="filter !== 'all'"
                x-cloak
                x-on:click="filter = 'all'"
                class="rounded-full px-2.5 py-1 text-[11px] font-semibold text-primary-700 hover:underline dark:text-primary-300"
            >
                Clear filter
            </button>
        </div>

        <input
            type="search"
            x-model.debounce.150ms="q"
            placeholder="Search name, roll, or mobile…"
            class="fi-input block w-full rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
        />

        <p x-show="filter === 'absent'" x-cloak class="text-xs font-medium text-rose-700 dark:text-rose-300">
            Absent list ({{ $absent }}) — no IN punch today.
        </p>
        <p x-show="filter === 'present'" x-cloak class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
            Present list ({{ $present }}).
        </p>
    </div>

    <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="hidden grid-cols-[minmax(0,1.4fr)_7rem_7rem_6.5rem_9rem] gap-2 border-b border-gray-100 bg-gray-50 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 md:grid">
            <div>Student</div>
            <div>IN</div>
            <div>OUT</div>
            <div>Source</div>
            <div class="text-right">Actions</div>
        </div>

        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @foreach ($rows as $row)
                <div
                    wire:key="manual-student-{{ $row['id'] }}"
                    x-show="matches({{ \Illuminate\Support\Js::from([
                        'name' => $row['name'],
                        'roll' => $row['roll'],
                        'mobile' => $row['mobile'],
                        'attendance' => $row['attendance'],
                        'track' => $row['track'],
                    ]) }})"
                    @class([
                        'grid grid-cols-1 items-center gap-2 px-3 py-2 md:grid-cols-[minmax(0,1.4fr)_7rem_7rem_6.5rem_9rem] md:gap-2',
                        'bg-white dark:bg-gray-900' => $row['attendance'] === 'absent',
                        'bg-emerald-50/50 dark:bg-emerald-500/5' => $row['track'] === 'in',
                        'bg-gray-50/70 dark:bg-white/[0.03]' => $row['track'] === 'out',
                    ])
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[11px] font-bold',
                                'bg-rose-500/15 text-rose-800 dark:text-rose-300' => $row['attendance'] === 'absent',
                                'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300' => $row['track'] === 'in',
                                'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $row['track'] === 'out',
                            ])>
                                {{ strtoupper(substr($row['name'], 0, 1)) }}
                            </span>
                            <div class="min-w-0">
                                <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                                <p class="truncate text-[11px] text-gray-500 dark:text-gray-400">
                                    @if (filled($row['roll']))
                                        <span class="font-mono">{{ $row['roll'] }}</span>
                                    @else
                                        <span class="text-amber-600">No roll</span>
                                    @endif
                                    @if (filled($row['mobile']))
                                        <span class="text-gray-300 dark:text-gray-600">·</span> {{ $row['mobile'] }}
                                    @endif
                                    @if ($row['attendance'] === 'absent')
                                        <span class="ml-1 rounded bg-rose-500/10 px-1 py-0.5 text-[10px] font-bold uppercase text-rose-700 dark:text-rose-300">Absent</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="flex items-center justify-between gap-2 md:block">
                        <span class="text-[10px] font-semibold uppercase text-gray-400 md:hidden">IN</span>
                        @if ($row['checked_in'])
                            <span class="font-mono text-sm font-semibold text-emerald-700 dark:text-emerald-300">{{ $row['checked_in'] }}</span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 md:block">
                        <span class="text-[10px] font-semibold uppercase text-gray-400 md:hidden">OUT</span>
                        @if ($row['checked_out'])
                            <span class="font-mono text-sm font-semibold text-rose-700 dark:text-rose-300">{{ $row['checked_out'] }}</span>
                        @elseif ($row['is_inside'])
                            <span class="inline-flex items-center gap-1 text-xs font-bold uppercase text-emerald-700 dark:text-emerald-300">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span>
                                Inside
                            </span>
                        @else
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    <div class="flex items-center justify-between gap-2 md:block">
                        <span class="text-[10px] font-semibold uppercase text-gray-400 md:hidden">Source</span>
                        @if ($row['checked_in'] || $row['checked_out'])
                            <span @class([
                                'inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                'bg-sky-500/10 text-sky-800 dark:text-sky-300' => ($row['source'] ?? null) === 'biometric' || ($row['source'] ?? null) === 'punch',
                                'bg-violet-500/10 text-violet-800 dark:text-violet-300' => ($row['source'] ?? null) === 'manual',
                                'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-300' => ! in_array($row['source'] ?? null, ['biometric', 'punch', 'manual'], true),
                            ])>
                                {{ $row['source_label'] }}
                            </span>
                        @else
                            <span class="text-xs text-gray-400">—</span>
                        @endif
                    </div>

                    <div class="flex justify-end">
                        <div class="inline-flex overflow-hidden rounded-lg bg-gray-100 p-0.5 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                            <button
                                type="button"
                                wire:click="markManualInForStudent({{ $row['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $row['id'] }})"
                                @class([
                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:opacity-60',
                                    'bg-emerald-500 text-white' => $row['checked_in'] !== null,
                                    'text-gray-500 hover:bg-white hover:text-emerald-700 dark:hover:bg-white/10' => $row['checked_in'] === null,
                                ])
                                title="Manual check-in"
                            >
                                <span wire:loading.remove wire:target="markManualInForStudent({{ $row['id'] }})">IN</span>
                                <span wire:loading wire:target="markManualInForStudent({{ $row['id'] }})">…</span>
                            </button>
                            <button
                                type="button"
                                wire:click="markManualOutForStudent({{ $row['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualOutForStudent({{ $row['id'] }})"
                                @disabled($row['checked_in'] === null)
                                @class([
                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-40',
                                    'bg-rose-500 text-white' => $row['checked_out'] !== null,
                                    'text-gray-500 hover:bg-white hover:text-rose-700 dark:hover:bg-white/10' => $row['checked_out'] === null,
                                ])
                                title="Manual check-out"
                            >
                                <span wire:loading.remove wire:target="markManualOutForStudent({{ $row['id'] }})">OUT</span>
                                <span wire:loading wire:target="markManualOutForStudent({{ $row['id'] }})">…</span>
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>

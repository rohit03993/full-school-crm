@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    /** @var array<int, array{status?: ?string, checked_in_at: ?string, checked_out_at: ?string, is_inside: bool, can_in?: bool, can_out?: bool, can_leave?: bool, can_absent?: bool, visit_count?: int, pairs?: list<array<string, mixed>>, punch_source?: ?string, marked_by_name?: ?string, source_label?: string, leave_reason?: ?string}> $attendanceSnapshot */

    $rows = $roster->map(function ($row) use ($attendanceSnapshot): array {
        $student = $row->student;
        $snapshot = $attendanceSnapshot[$student->id] ?? null;
        $checkedIn = $snapshot['checked_in_at'] ?? null;
        $checkedOut = $snapshot['checked_out_at'] ?? null;
        $isInside = (bool) ($snapshot['is_inside'] ?? false);
        $canIn = (bool) ($snapshot['can_in'] ?? ! $isInside);
        $canOut = (bool) ($snapshot['can_out'] ?? $isInside);
        $canLeave = (bool) ($snapshot['can_leave'] ?? false);
        $canAbsent = (bool) ($snapshot['can_absent'] ?? false);
        $pairs = $snapshot['pairs'] ?? [];
        $visitCount = (int) ($snapshot['visit_count'] ?? count($pairs));
        $source = $snapshot['punch_source'] ?? null;
        $staffName = $snapshot['marked_by_name'] ?? null;
        $statusValue = $snapshot['status'] ?? null;
        $leaveReason = $snapshot['leave_reason'] ?? null;

        if ($statusValue === 'leave') {
            $attendance = 'leave';
        } elseif ($checkedIn !== null || $visitCount > 0 || $statusValue === 'present') {
            $attendance = 'present';
        } else {
            $attendance = 'absent';
        }

        $track = $isInside ? 'in' : ($visitCount > 0 || $checkedOut !== null ? 'out' : 'pending');
        $roll = $student->activeEnrollment?->enrollment_number;

        return [
            'id' => $student->id,
            'name' => $student->name,
            'mobile' => $student->mobile,
            'roll' => $roll,
            'checked_in' => $checkedIn,
            'checked_out' => $checkedOut,
            'is_inside' => $isInside,
            'can_in' => $canIn,
            'can_out' => $canOut,
            'can_leave' => $canLeave,
            'can_absent' => $canAbsent,
            'pairs' => $pairs,
            'visit_count' => $visitCount,
            'attendance' => $attendance,
            'leave_reason' => $leaveReason,
            'track' => $track,
            'source' => $source,
            'source_label' => $snapshot['source_label']
                ?? \App\Support\AttendanceSourceLabel::for($source, $staffName),
            'source_is_manual' => \App\Support\AttendanceSourceLabel::isManual($source),
            'sort' => match ($attendance) {
                'leave' => 0,
                'absent' => 1,
                default => $track === 'in' ? 2 : 3,
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
    $leave = $rows->where('attendance', 'leave')->count();
    $inside = $rows->where('track', 'in')->count();
    $checkedOutCount = $rows->where('track', 'out')->count();
    $percentage = $enrolled > 0 ? round(($present / $enrolled) * 100, 1) : 0;
    $leaveTags = \App\Support\AttendanceLeaveReasons::tags();
@endphp

<div
    wire:key="manual-attendance-roster"
    x-data="{
        q: '',
        filter: 'all',
        matches(row) {
            if (this.filter === 'present' && row.attendance !== 'present') return false;
            if (this.filter === 'absent' && row.attendance !== 'absent') return false;
            if (this.filter === 'leave' && row.attendance !== 'leave') return false;
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
    <div class="fi-section space-y-3 rounded-2xl bg-white/95 px-3 py-3 shadow-sm ring-1 ring-gray-950/5 backdrop-blur dark:bg-gray-900/95 dark:ring-white/10 sm:px-4 lg:sticky lg:top-0 lg:z-10">
        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm font-bold text-gray-950 dark:text-white">
                    Class attendance today
                </p>
                <p class="text-[11px] text-gray-500 dark:text-gray-400">
                    Present = IN today. For students who did not come: mark Absent or Leave (Leave asks for a reason). After checkout / 8:00 PM auto-out, OUT stays highlighted.
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

        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
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
                <p class="mt-0.5 text-[10px] text-rose-600/80 dark:text-rose-300/80">Not came</p>
            </button>

            <button
                type="button"
                x-on:click="filter = 'leave'"
                x-bind:class="filter === 'leave'
                    ? 'ring-2 ring-amber-500 bg-amber-50 dark:bg-amber-500/10'
                    : 'ring-1 ring-gray-200 dark:ring-white/10 hover:bg-gray-50 dark:hover:bg-white/5'"
                class="rounded-xl px-3 py-2.5 text-left transition"
            >
                <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Leave</p>
                <p class="text-xl font-bold tabular-nums text-amber-700 dark:text-amber-300">{{ $leave }}</p>
                <p class="mt-0.5 text-[10px] text-amber-700/80 dark:text-amber-300/80">With reason</p>
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
        <p x-show="filter === 'leave'" x-cloak class="text-xs font-medium text-amber-700 dark:text-amber-300">
            Leave list ({{ $leave }}) — marked with reason.
        </p>
        <p x-show="filter === 'present'" x-cloak class="text-xs font-medium text-emerald-700 dark:text-emerald-300">
            Present list ({{ $present }}).
        </p>
    </div>

    <div class="fi-section overflow-hidden rounded-2xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="hidden grid-cols-[minmax(0,1.1fr)_minmax(0,1.5fr)_minmax(11rem,auto)] gap-3 border-b border-gray-100 bg-gray-50 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 md:grid">
            <div>Student</div>
            <div>Visits · source / leave reason</div>
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
                        'grid grid-cols-1 items-start gap-3 px-3 py-3 md:grid-cols-[minmax(0,1.1fr)_minmax(0,1.5fr)_minmax(11rem,auto)] md:items-center',
                        'bg-white dark:bg-gray-900' => $row['attendance'] === 'absent',
                        'bg-amber-50/60 dark:bg-amber-500/5' => $row['attendance'] === 'leave',
                        'bg-emerald-50/50 dark:bg-emerald-500/5' => $row['track'] === 'in' && $row['attendance'] === 'present',
                        'bg-gray-50/70 dark:bg-white/[0.03]' => $row['track'] === 'out' && $row['attendance'] === 'present',
                    ])
                >
                    <div class="min-w-0">
                        <div class="flex items-center gap-2">
                            <span @class([
                                'flex h-7 w-7 shrink-0 items-center justify-center rounded-md text-[11px] font-bold',
                                'bg-rose-500/15 text-rose-800 dark:text-rose-300' => $row['attendance'] === 'absent',
                                'bg-amber-500/15 text-amber-900 dark:text-amber-300' => $row['attendance'] === 'leave',
                                'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300' => $row['track'] === 'in' && $row['attendance'] === 'present',
                                'bg-gray-200 text-gray-600 dark:bg-white/10 dark:text-gray-300' => $row['track'] === 'out' && $row['attendance'] === 'present',
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
                                    @if ($row['attendance'] === 'leave')
                                        <span class="ml-1 rounded bg-amber-500/10 px-1 py-0.5 text-[10px] font-bold uppercase text-amber-800 dark:text-amber-300">Leave</span>
                                    @elseif ($row['attendance'] === 'absent')
                                        <span class="ml-1 rounded bg-rose-500/10 px-1 py-0.5 text-[10px] font-bold uppercase text-rose-700 dark:text-rose-300">Absent</span>
                                    @elseif ($row['is_inside'])
                                        <span class="ml-1 rounded bg-emerald-500/10 px-1 py-0.5 text-[10px] font-bold uppercase text-emerald-700 dark:text-emerald-300">Inside</span>
                                    @elseif ($row['visit_count'] > 0)
                                        <span class="ml-1 rounded bg-gray-500/10 px-1 py-0.5 text-[10px] font-bold uppercase text-gray-600 dark:text-gray-300">Out</span>
                                    @endif
                                </p>
                            </div>
                        </div>
                    </div>

                    <div class="min-w-0">
                        <span class="mb-1.5 block text-[10px] font-semibold uppercase text-gray-400 md:hidden">Visits</span>
                        @if ($row['attendance'] === 'leave')
                            <p class="text-sm font-medium text-amber-900 dark:text-amber-200">
                                {{ filled($row['leave_reason']) ? $row['leave_reason'] : 'On leave' }}
                            </p>
                        @elseif ($row['pairs'] !== [])
                            <div class="space-y-2.5">
                                @foreach ($row['pairs'] as $index => $pair)
                                    <div class="rounded-lg bg-white/80 px-2.5 py-2 ring-1 ring-gray-950/5 dark:bg-gray-950/40 dark:ring-white/10">
                                        <div class="mb-1.5 flex items-center justify-between gap-2">
                                            <span class="text-[9px] font-bold uppercase tracking-wide text-gray-400">Visit {{ $index + 1 }}</span>
                                            @if (filled($pair['duration_label'] ?? null))
                                                <span class="text-[10px] text-gray-400">{{ $pair['duration_label'] }}</span>
                                            @endif
                                        </div>
                                        <div class="grid grid-cols-2 gap-3">
                                            <div class="min-w-0">
                                                <p class="text-[9px] font-semibold uppercase text-emerald-600 dark:text-emerald-400">In</p>
                                                <p class="font-mono text-[12px] font-bold text-emerald-700 dark:text-emerald-300">
                                                    {{ filled($pair['in'] ?? null) ? substr((string) $pair['in'], 0, 5) : '—' }}
                                                </p>
                                                @include('filament.pages.partials.punch-source-chip', [
                                                    'isManual' => ! empty($pair['is_manual_in']),
                                                    'device' => $pair['device_in'] ?? null,
                                                    'staffName' => $pair['marked_by_in'] ?? null,
                                                ])
                                            </div>
                                            <div class="min-w-0">
                                                <p class="text-[9px] font-semibold uppercase text-rose-600 dark:text-rose-400">Out</p>
                                                @if (filled($pair['out'] ?? null))
                                                    <p class="font-mono text-[12px] font-bold text-rose-700 dark:text-rose-300">
                                                        {{ substr((string) $pair['out'], 0, 5) }}
                                                    </p>
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
                            <span class="text-sm text-gray-400">—</span>
                        @endif
                    </div>

                    <div class="flex flex-col items-stretch justify-end gap-1.5 sm:items-end">
                        <div class="inline-flex overflow-hidden rounded-lg bg-gray-100 p-0.5 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                            @php
                                $inIsCurrent = $row['is_inside'] || ($row['track'] === 'pending' && $row['attendance'] !== 'leave' && $row['attendance'] !== 'absent');
                                $outIsCurrent = ! $row['is_inside'] && $row['track'] === 'out';
                            @endphp
                            <button
                                type="button"
                                wire:click="markManualInForStudent({{ $row['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $row['id'] }}),markManualOutForStudent({{ $row['id'] }})"
                                @disabled(! $row['can_in'])
                                @class([
                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                    'bg-emerald-500 text-white shadow-sm' => $inIsCurrent,
                                    'bg-white text-emerald-700 ring-1 ring-emerald-300 dark:bg-transparent dark:text-emerald-300 dark:ring-emerald-500/40' => ! $inIsCurrent && $row['can_in'],
                                    'text-gray-400' => ! $inIsCurrent && ! $row['can_in'],
                                ])
                                title="{{ $row['is_inside'] ? 'Currently inside' : ($row['can_in'] ? 'Manual check-in' : 'Already inside — mark OUT first') }}"
                            >
                                <span wire:loading.remove wire:target="markManualInForStudent({{ $row['id'] }})">IN</span>
                                <span wire:loading wire:target="markManualInForStudent({{ $row['id'] }})">…</span>
                            </button>
                            <button
                                type="button"
                                wire:click="markManualOutForStudent({{ $row['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $row['id'] }}),markManualOutForStudent({{ $row['id'] }})"
                                @disabled(! $row['can_out'])
                                @class([
                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                    'bg-rose-500 text-white shadow-sm' => $outIsCurrent,
                                    'bg-white text-rose-700 ring-1 ring-rose-300 dark:bg-transparent dark:text-rose-300 dark:ring-rose-500/40' => ! $outIsCurrent && $row['can_out'],
                                    'text-gray-400' => ! $outIsCurrent && ! $row['can_out'],
                                ])
                                title="{{ $outIsCurrent ? 'Currently checked out (incl. auto-out after 8:00 PM)' : ($row['can_out'] ? 'Manual check-out' : 'Not inside — mark IN first') }}"
                            >
                                <span wire:loading.remove wire:target="markManualOutForStudent({{ $row['id'] }})">OUT</span>
                                <span wire:loading wire:target="markManualOutForStudent({{ $row['id'] }})">…</span>
                            </button>
                        </div>
                        @if ($row['can_absent'] || $row['can_leave'] || $row['attendance'] === 'leave' || $row['attendance'] === 'absent')
                            <div class="inline-flex flex-wrap justify-end gap-1">
                                <button
                                    type="button"
                                    wire:click="markAbsentForStudent({{ $row['id'] }})"
                                    wire:loading.attr="disabled"
                                    @disabled(! $row['can_absent'])
                                    @class([
                                        'rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                        'bg-rose-600 text-white' => $row['attendance'] === 'absent',
                                        'bg-white text-rose-700 ring-1 ring-rose-200 dark:bg-transparent dark:text-rose-300' => $row['attendance'] !== 'absent',
                                    ])
                                >
                                    Absent
                                </button>
                                <button
                                    type="button"
                                    wire:click="openLeaveModal({{ $row['id'] }})"
                                    wire:loading.attr="disabled"
                                    @disabled(! $row['can_leave'])
                                    @class([
                                        'rounded-md px-2 py-1 text-[10px] font-bold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                        'bg-amber-500 text-white' => $row['attendance'] === 'leave',
                                        'bg-white text-amber-800 ring-1 ring-amber-200 dark:bg-transparent dark:text-amber-300' => $row['attendance'] !== 'leave',
                                    ])
                                >
                                    Leave
                                </button>
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>

    {{-- Leave reason modal --}}
    @if ($this->showLeaveModal)
        <div
            class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
            wire:click.self="closeLeaveModal"
        >
            <div
                class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900"
                wire:click.stop
            >
                <h3 class="text-base font-bold text-gray-950 dark:text-white">Mark Leave</h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                    {{ $this->leaveStudentName }} — pick a reason tag and/or type your own.
                </p>

                <div class="mt-4 flex flex-wrap gap-2">
                    @foreach ($leaveTags as $tag)
                        <button
                            type="button"
                            wire:click="selectLeaveTag(@js($tag))"
                            @class([
                                'rounded-full px-3 py-1.5 text-xs font-semibold ring-1 transition',
                                'bg-amber-500 text-white ring-amber-500' => $this->leaveTag === $tag,
                                'bg-amber-50 text-amber-900 ring-amber-200 hover:bg-amber-100 dark:bg-amber-500/10 dark:text-amber-200 dark:ring-amber-500/30' => $this->leaveTag !== $tag,
                            ])
                        >
                            {{ $tag }}
                        </button>
                    @endforeach
                </div>

                <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-gray-500">
                    Custom reason (optional if a tag is selected)
                </label>
                <textarea
                    wire:model="leaveCustomReason"
                    rows="2"
                    maxlength="255"
                    placeholder="Type a custom reason…"
                    class="fi-crm-input mt-1 block w-full"
                ></textarea>

                <div class="mt-5 flex justify-end gap-2">
                    <button
                        type="button"
                        wire:click="closeLeaveModal"
                        class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        wire:click="confirmLeave"
                        wire:loading.attr="disabled"
                        class="rounded-lg bg-amber-500 px-3 py-2 text-sm font-bold text-white hover:bg-amber-400 disabled:opacity-60"
                    >
                        <span wire:loading.remove wire:target="confirmLeave">Save Leave</span>
                        <span wire:loading wire:target="confirmLeave">Saving…</span>
                    </button>
                </div>
            </div>
        </div>
    @endif
</div>

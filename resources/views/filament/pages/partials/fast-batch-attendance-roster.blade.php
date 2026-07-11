@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\BatchStudent> $roster */
    /** @var array<int, array{status?: ?string, checked_in_at: ?string, checked_out_at: ?string, is_inside: bool, can_in?: bool, can_out?: bool, visit_count?: int, pairs?: list<array<string, mixed>>, punch_source?: ?string, marked_by_name?: ?string, source_label?: string}> $attendanceSnapshot */

    $rows = $roster->map(function ($row) use ($attendanceSnapshot): array {
        $student = $row->student;
        $snapshot = $attendanceSnapshot[$student->id] ?? null;
        $checkedIn = $snapshot['checked_in_at'] ?? null;
        $checkedOut = $snapshot['checked_out_at'] ?? null;
        $isInside = (bool) ($snapshot['is_inside'] ?? false);
        $canIn = (bool) ($snapshot['can_in'] ?? ! $isInside);
        $canOut = (bool) ($snapshot['can_out'] ?? $isInside);
        $pairs = $snapshot['pairs'] ?? [];
        $visitCount = (int) ($snapshot['visit_count'] ?? count($pairs));
        $source = $snapshot['punch_source'] ?? null;
        $staffName = $snapshot['marked_by_name'] ?? null;
        $attendance = ($checkedIn !== null || $visitCount > 0) ? 'present' : 'absent';
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
            'pairs' => $pairs,
            'visit_count' => $visitCount,
            'attendance' => $attendance,
            'track' => $track,
            'source' => $source,
            'source_label' => $snapshot['source_label']
                ?? \App\Support\AttendanceSourceLabel::for($source, $staffName),
            'source_is_manual' => \App\Support\AttendanceSourceLabel::isManual($source),
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
                    Present = IN at least once today. Visits list every IN→OUT pair. Only the next action is enabled (IN or OUT).
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
        <div class="hidden grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)_9rem] gap-3 border-b border-gray-100 bg-gray-50 px-3 py-2 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400 md:grid">
            <div>Student</div>
            <div>Visits · source per punch</div>
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
                        'grid grid-cols-1 items-start gap-3 px-3 py-3 md:grid-cols-[minmax(0,1.15fr)_minmax(0,1.85fr)_9rem] md:items-center',
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
                        @if ($row['pairs'] !== [])
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

                    <div class="flex justify-end">
                        <div class="inline-flex overflow-hidden rounded-lg bg-gray-100 p-0.5 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                            <button
                                type="button"
                                wire:click="markManualInForStudent({{ $row['id'] }})"
                                wire:loading.attr="disabled"
                                wire:target="markManualInForStudent({{ $row['id'] }}),markManualOutForStudent({{ $row['id'] }})"
                                @disabled(! $row['can_in'])
                                @class([
                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                    'bg-emerald-500 text-white shadow-sm' => $row['can_in'],
                                    'text-gray-400' => ! $row['can_in'],
                                ])
                                title="{{ $row['can_in'] ? 'Manual check-in' : 'Already inside — mark OUT first' }}"
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
                                    'bg-rose-500 text-white shadow-sm' => $row['can_out'],
                                    'text-gray-400' => ! $row['can_out'],
                                ])
                                title="{{ $row['can_out'] ? 'Manual check-out' : 'Not inside — mark IN first' }}"
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

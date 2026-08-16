@php
    $summary = $summary ?? ['total' => 0, 'present' => 0, 'inside' => 0, 'left' => 0, 'not_punched' => 0, 'leave' => 0];
    $missingStaffIdCount = (int) ($missingStaffIdCount ?? 0);
@endphp

<div class="space-y-3">
    @if ($missingStaffIdCount > 0)
        <div class="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-950 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-100">
            <strong>{{ $missingStaffIdCount }}</strong> active staff still have no Staff ID, so they cannot punch or appear below.
            <a href="{{ $staffListUrl }}" wire:navigate class="font-semibold underline underline-offset-2">
                Open Staff → Assign missing Staff IDs
            </a>
            , then Sync to Face API / enroll the same PIN on the ADMS machine.
        </div>
    @endif

    <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
        <div class="rounded-xl border border-gray-200 bg-white px-3 py-2.5 dark:border-white/10 dark:bg-gray-900">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">With Staff ID</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-gray-950 dark:text-white">{{ $summary['total'] }}</p>
        </div>
        <div class="rounded-xl border border-emerald-200/80 bg-emerald-50/80 px-3 py-2.5 dark:border-emerald-500/20 dark:bg-emerald-500/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700 dark:text-emerald-300">Present</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-emerald-800 dark:text-emerald-200">{{ $summary['present'] }}</p>
            <p class="text-[11px] text-emerald-700/80 dark:text-emerald-300/80">{{ $summary['inside'] }} inside · {{ $summary['left'] }} left</p>
        </div>
        <div class="rounded-xl border border-amber-200/80 bg-amber-50/70 px-3 py-2.5 dark:border-amber-500/20 dark:bg-amber-500/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">Not punched</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-amber-900 dark:text-amber-100">{{ $summary['not_punched'] }}</p>
        </div>
        <div class="rounded-xl border border-sky-200/80 bg-sky-50/70 px-3 py-2.5 dark:border-sky-500/20 dark:bg-sky-500/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-sky-700 dark:text-sky-300">Leave</p>
            <p class="mt-1 text-xl font-bold tabular-nums text-sky-900 dark:text-sky-100">{{ $summary['leave'] }}</p>
        </div>
        <div class="col-span-2 rounded-xl border border-gray-200 bg-gray-50 px-3 py-2.5 sm:col-span-3 lg:col-span-1 dark:border-white/10 dark:bg-white/5">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Day</p>
            <p class="mt-1 text-sm font-semibold text-gray-800 dark:text-gray-100">{{ \Illuminate\Support\Carbon::parse($date)->format('d M Y') }}</p>
            <p class="text-[11px] text-gray-500">Staff ID = device PIN = Face ID</p>
        </div>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
        {{-- Mobile cards --}}
        <div class="space-y-2 p-3 lg:hidden">
            @forelse ($rows as $row)
                <div
                    wire:key="staff-att-card-{{ $row['id'] }}-{{ $date }}"
                    class="rounded-xl bg-gray-50 p-3 ring-1 ring-gray-950/5 dark:bg-white/5 dark:ring-white/10"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-950 dark:text-white">{{ $row['name'] }}</p>
                            <p class="mt-0.5 font-mono text-[11px] text-gray-500">{{ $row['employee_code'] }}</p>
                            @if ($row['designation'])
                                <p class="mt-0.5 text-xs text-gray-600 dark:text-gray-300">{{ $row['designation'] }}</p>
                            @endif
                        </div>
                        <span @class([
                            'shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                            'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/30' => in_array($row['status_key'], ['inside', 'left'], true),
                            'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-500/30' => ($row['status_key'] ?? '') === 'not_punched',
                            'bg-sky-50 text-sky-900 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-100 dark:ring-sky-500/30' => ($row['status_key'] ?? '') === 'leave',
                            'bg-white text-gray-700 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10' => ! in_array($row['status_key'] ?? '', ['inside', 'left', 'not_punched', 'leave'], true),
                        ])>
                            {{ $row['status_label'] ?? '—' }}
                        </span>
                    </div>

                    <div class="mt-3 grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <p class="font-semibold uppercase tracking-wide text-gray-500">IN</p>
                            <p class="mt-0.5 font-semibold text-emerald-700 dark:text-emerald-300">{{ $row['checked_in_at'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold uppercase tracking-wide text-gray-500">OUT</p>
                            <p class="mt-0.5 font-semibold text-rose-700 dark:text-rose-300">{{ $row['checked_out_at'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold uppercase tracking-wide text-gray-500">Mobile</p>
                            <p class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $row['mobile'] ?: '—' }}</p>
                        </div>
                        <div>
                            <p class="font-semibold uppercase tracking-wide text-gray-500">Source</p>
                            <p class="mt-0.5 text-gray-700 dark:text-gray-200">{{ $row['source_label'] ?? ($row['punch_source'] ?: '—') }}</p>
                        </div>
                    </div>

                    <div class="mt-3 border-t border-gray-200/80 pt-3 dark:border-white/10">
                        @if ($canMarkToday)
                            <div class="inline-flex w-full overflow-hidden rounded-lg bg-gray-100 p-0.5 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                                <button
                                    type="button"
                                    wire:click="markManualIn({{ $row['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="markManualIn({{ $row['id'] }}),markManualOut({{ $row['id'] }})"
                                    @disabled(! $row['can_in'])
                                    @class([
                                        'min-h-11 flex-1 touch-manipulation rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                        'bg-emerald-500 text-white shadow-sm' => $row['can_in'],
                                        'text-gray-400' => ! $row['can_in'],
                                    ])
                                >
                                    <span wire:loading.remove wire:target="markManualIn({{ $row['id'] }})">IN</span>
                                    <span wire:loading wire:target="markManualIn({{ $row['id'] }})">…</span>
                                </button>
                                <button
                                    type="button"
                                    wire:click="markManualOut({{ $row['id'] }})"
                                    wire:loading.attr="disabled"
                                    wire:target="markManualIn({{ $row['id'] }}),markManualOut({{ $row['id'] }})"
                                    @disabled(! $row['can_out'])
                                    @class([
                                        'min-h-11 flex-1 touch-manipulation rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                        'bg-rose-500 text-white shadow-sm' => $row['can_out'],
                                        'text-gray-400' => ! $row['can_out'],
                                    ])
                                >
                                    <span wire:loading.remove wire:target="markManualOut({{ $row['id'] }})">OUT</span>
                                    <span wire:loading wire:target="markManualOut({{ $row['id'] }})">…</span>
                                </button>
                            </div>
                        @else
                            <p class="text-center text-xs text-gray-400">Manual mark — today only</p>
                        @endif
                    </div>
                </div>
            @empty
                <p class="px-2 py-8 text-center text-sm text-gray-500">
                    No staff with Staff ID for {{ $date }}.
                    @if ($missingStaffIdCount > 0)
                        <a href="{{ $staffListUrl }}" wire:navigate class="font-semibold text-primary-700 underline dark:text-primary-300">Assign missing Staff IDs</a>
                        first, then return here.
                    @else
                        Add staff under Admin → Staff (IDs auto-assign from 1001).
                    @endif
                </p>
            @endforelse
        </div>

        {{-- Desktop table --}}
        <div class="hidden overflow-x-auto lg:block">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-3">Staff ID</th>
                        <th class="px-4 py-3">Name</th>
                        <th class="px-4 py-3">Designation</th>
                        <th class="px-4 py-3">Mobile</th>
                        <th class="px-4 py-3">IN</th>
                        <th class="px-4 py-3">OUT</th>
                        <th class="px-4 py-3">Status</th>
                        <th class="px-4 py-3">Source</th>
                        <th class="px-4 py-3 text-right">Mark</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                    @forelse ($rows as $row)
                        <tr wire:key="staff-att-{{ $row['id'] }}-{{ $date }}">
                            <td class="px-4 py-3 font-mono text-xs">{{ $row['employee_code'] }}</td>
                            <td class="px-4 py-3 font-medium">{{ $row['name'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['designation'] ?: '—' }}</td>
                            <td class="px-4 py-3">{{ $row['mobile'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-emerald-700 dark:text-emerald-300">{{ $row['checked_in_at'] ?: '—' }}</td>
                            <td class="px-4 py-3 text-rose-700 dark:text-rose-300">{{ $row['checked_out_at'] ?: '—' }}</td>
                            <td class="px-4 py-3">
                                <span @class([
                                    'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1',
                                    'bg-emerald-50 text-emerald-800 ring-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-200 dark:ring-emerald-500/30' => in_array($row['status_key'], ['inside', 'left'], true),
                                    'bg-amber-50 text-amber-900 ring-amber-200 dark:bg-amber-500/15 dark:text-amber-100 dark:ring-amber-500/30' => ($row['status_key'] ?? '') === 'not_punched',
                                    'bg-sky-50 text-sky-900 ring-sky-200 dark:bg-sky-500/15 dark:text-sky-100 dark:ring-sky-500/30' => ($row['status_key'] ?? '') === 'leave',
                                ])>
                                    {{ $row['status_label'] ?? '—' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-gray-500">{{ $row['source_label'] ?? ($row['punch_source'] ?: '—') }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end">
                                    @if ($canMarkToday)
                                        <div class="inline-flex overflow-hidden rounded-lg bg-gray-100 p-0.5 ring-1 ring-gray-200/80 dark:bg-white/5 dark:ring-white/10">
                                            <button
                                                type="button"
                                                wire:click="markManualIn({{ $row['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="markManualIn({{ $row['id'] }}),markManualOut({{ $row['id'] }})"
                                                @disabled(! $row['can_in'])
                                                @class([
                                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                                    'bg-emerald-500 text-white shadow-sm' => $row['can_in'],
                                                    'text-gray-400' => ! $row['can_in'],
                                                ])
                                                title="{{ $row['can_in'] ? 'Manual check-in' : 'Already inside — mark OUT first' }}"
                                            >
                                                <span wire:loading.remove wire:target="markManualIn({{ $row['id'] }})">IN</span>
                                                <span wire:loading wire:target="markManualIn({{ $row['id'] }})">…</span>
                                            </button>
                                            <button
                                                type="button"
                                                wire:click="markManualOut({{ $row['id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="markManualIn({{ $row['id'] }}),markManualOut({{ $row['id'] }})"
                                                @disabled(! $row['can_out'])
                                                @class([
                                                    'min-w-[3.25rem] rounded-md px-2.5 py-1.5 text-xs font-extrabold uppercase tracking-wide transition disabled:cursor-not-allowed disabled:opacity-35',
                                                    'bg-rose-500 text-white shadow-sm' => $row['can_out'],
                                                    'text-gray-400' => ! $row['can_out'],
                                                ])
                                                title="{{ $row['can_out'] ? 'Manual check-out' : 'Not inside — mark IN first' }}"
                                            >
                                                <span wire:loading.remove wire:target="markManualOut({{ $row['id'] }})">OUT</span>
                                                <span wire:loading wire:target="markManualOut({{ $row['id'] }})">…</span>
                                            </button>
                                        </div>
                                    @else
                                        <span class="text-xs text-gray-400" title="Manual mark only allowed for today">Today only</span>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-8 text-center text-gray-500">
                                No staff with Staff ID for {{ $date }}.
                                @if ($missingStaffIdCount > 0)
                                    <a href="{{ $staffListUrl }}" wire:navigate class="font-semibold text-primary-700 underline dark:text-primary-300">Assign missing Staff IDs</a>
                                    first, then return here.
                                @else
                                    Add staff under Admin → Staff (IDs auto-assign from 1001).
                                @endif
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

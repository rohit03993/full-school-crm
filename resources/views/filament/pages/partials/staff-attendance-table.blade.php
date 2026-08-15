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
                    <span class="shrink-0 rounded-full bg-white px-2 py-0.5 text-[11px] font-semibold text-gray-700 ring-1 ring-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10">
                        {{ $row['status'] ?: '—' }}
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
                No staff with Staff ID found for {{ $date }}.
                Import staff under Admin → Import staff, then sync faces from Admin → Staff.
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
                        <td class="px-4 py-3">{{ $row['status'] ?: '—' }}</td>
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
                            No staff with Staff ID found for {{ $date }}.
                            Import staff under Admin → Import staff, then sync faces from Admin → Staff.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

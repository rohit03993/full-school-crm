<div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
    <div class="overflow-x-auto">
        <table class="w-full min-w-[54rem] text-left text-sm">
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

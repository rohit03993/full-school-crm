<div class="space-y-4">
    @if (($scopeChargeSummaries ?? collect())->isNotEmpty())
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Misc charges on selected group</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Existing separate charges for the batch or course you selected above.</p>
            </div>
            <x-crm.responsive-table>
                <table class="w-full min-w-[720px] text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold sm:px-6">Charge</th>
                            <th class="px-4 py-3 font-semibold">Students</th>
                            <th class="px-4 py-3 font-semibold">Paid</th>
                            <th class="px-4 py-3 font-semibold">Pending</th>
                            <th class="px-4 py-3 font-semibold">Due</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($scopeChargeSummaries as $row)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="crm-responsive-table__title px-4 py-3 sm:px-6" data-label="">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">₹{{ number_format((float) $row['amount'], 2) }} each</p>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Students">{{ (int) $row['student_count'] }}</td>
                                <td class="px-4 py-3 font-medium text-emerald-700 dark:text-emerald-400" data-label="Paid">₹{{ number_format((float) $row['paid_total'], 2) }}</td>
                                <td class="px-4 py-3 font-medium text-amber-700 dark:text-amber-300" data-label="Pending">₹{{ number_format((float) $row['pending_total'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Due">
                                    {{ filled($row['due_date'] ?? null) ? \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        </div>
    @endif

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent misc charges</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Bulk and per-student misc charges added recently.</p>
            </div>
            <x-filament::button color="gray" wire:click="refreshChargeSummaries" size="sm">
                Refresh
            </x-filament::button>
        </div>

        @if (($recentChargeSummaries ?? collect())->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No misc charges added yet.</p>
        @else
            <x-crm.responsive-table>
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold sm:px-6">Charge</th>
                            <th class="px-4 py-3 font-semibold">Students</th>
                            <th class="px-4 py-3 font-semibold">Paid</th>
                            <th class="px-4 py-3 font-semibold">Pending</th>
                            <th class="px-4 py-3 font-semibold">Added</th>
                            <th class="px-4 py-3 font-semibold">By</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($recentChargeSummaries as $row)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="crm-responsive-table__title px-4 py-3 sm:px-6" data-label="">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $row['label'] }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        ₹{{ number_format((float) $row['amount'], 2) }} each
                                        @if (filled($row['due_date'] ?? null))
                                            · due {{ \Illuminate\Support\Carbon::parse($row['due_date'])->format('d M Y') }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Students">{{ (int) $row['student_count'] }}</td>
                                <td class="px-4 py-3 font-medium text-emerald-700 dark:text-emerald-400" data-label="Paid">₹{{ number_format((float) $row['paid_total'], 2) }}</td>
                                <td class="px-4 py-3 font-medium text-amber-700 dark:text-amber-300" data-label="Pending">₹{{ number_format((float) $row['pending_total'], 2) }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Added">
                                    {{ $row['added_at']?->format('d M Y H:i') ?? '—' }}
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="By">{{ $row['added_by'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        @endif
    </div>
</div>

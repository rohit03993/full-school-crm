<x-filament-panels::page>
    <div class="space-y-6">
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <form wire:submit="applyFilters" class="flex flex-wrap items-end gap-4">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" for="ledger-from">From</label>
                    <input
                        id="ledger-from"
                        type="date"
                        wire:model="fromDate"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" for="ledger-to">To</label>
                    <input
                        id="ledger-to"
                        type="date"
                        wire:model="toDate"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <x-filament::button type="submit" size="sm">Apply</x-filament::button>
            </form>
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal entries</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['entry_count'] ?? 0) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total debits</p>
                <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) ($summary['total_debits'] ?? 0), 2) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total credits</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['total_credits'] ?? 0), 2) }}</p>
            </div>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Account balances</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">For the selected date range</p>
            </div>
            @if (($summary['accounts'] ?? collect())->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No ledger activity yet. Entries are created when fees are collected or late fees accrue.</p>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[640px] text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-6">Code</th>
                                <th class="px-4 py-3 font-semibold">Account</th>
                                <th class="px-4 py-3 font-semibold">Type</th>
                                <th class="px-4 py-3 font-semibold text-right">Debit</th>
                                <th class="px-4 py-3 font-semibold text-right">Credit</th>
                                <th class="px-4 py-3 font-semibold text-right">Balance</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($summary['accounts'] as $account)
                                <tr>
                                    <td class="px-4 py-3 font-mono text-xs sm:px-6">{{ $account['code'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $account['name'] }}</td>
                                    <td class="px-4 py-3 capitalize text-gray-600 dark:text-gray-300">{{ $account['type'] }}</td>
                                    <td class="px-4 py-3 text-right">₹{{ number_format((float) $account['debit'], 2) }}</td>
                                    <td class="px-4 py-3 text-right">₹{{ number_format((float) $account['credit'], 2) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold">₹{{ number_format((float) $account['balance'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent journal entries</h2>
            </div>
            @if ($entries->isEmpty())
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No entries in this period.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($entries as $entry)
                        <div class="px-4 py-4 sm:px-6">
                            <div class="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $entry->description }}</p>
                                    <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                        {{ $entry->entry_date?->format('d M Y') }}
                                        @if ($entry->postedBy)
                                            · {{ $entry->postedBy->name }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="mt-3 overflow-x-auto">
                                <table class="w-full min-w-[480px] text-left text-xs">
                                    <thead class="text-gray-500 dark:text-gray-400">
                                        <tr>
                                            <th class="py-1 pr-3 font-semibold">Account</th>
                                            <th class="py-1 pr-3 font-semibold text-right">Debit</th>
                                            <th class="py-1 font-semibold text-right">Credit</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($entry->lines as $line)
                                            <tr>
                                                <td class="py-1 pr-3 text-gray-800 dark:text-gray-200">
                                                    <span class="font-mono text-[11px] text-gray-500">{{ $line->account?->code }}</span>
                                                    {{ $line->account?->name }}
                                                </td>
                                                <td class="py-1 pr-3 text-right">@if ((float) $line->debit > 0) ₹{{ number_format((float) $line->debit, 2) }} @else — @endif</td>
                                                <td class="py-1 text-right">@if ((float) $line->credit > 0) ₹{{ number_format((float) $line->credit, 2) }} @else — @endif</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>

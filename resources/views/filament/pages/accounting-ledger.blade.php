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

        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal entries</p>
                <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['entry_count'] ?? 0) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total collected</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['total_collected'] ?? 0), 2) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash collected</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['cash_collected'] ?? 0), 2) }}</p>
            </div>
            <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bank / UPI collected</p>
                <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['bank_collected'] ?? 0), 2) }}</p>
            </div>
        </div>

        <div class="grid gap-4 lg:grid-cols-2">
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Collections summary</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Money received during the selected period</p>
                </div>
                @if (($summary['collection_rows'] ?? collect())->isEmpty())
                    <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No fee collections in this period.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[320px] text-left text-sm">
                            <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-semibold sm:px-6">Mode</th>
                                    <th class="px-4 py-3 font-semibold text-right">Credit (received)</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($summary['collection_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6">{{ $row['label'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $row['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Income breakdown</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Fee income recognized in this period</p>
                </div>
                @if (($summary['income_rows'] ?? collect())->isEmpty() && (float) ($summary['fees_receivable'] ?? 0) <= 0)
                    <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No fee income activity yet.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[320px] text-left text-sm">
                            <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                                <tr>
                                    <th class="px-4 py-3 font-semibold sm:px-6">Category</th>
                                    <th class="px-4 py-3 font-semibold text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                                @foreach ($summary['income_rows'] as $row)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6">{{ $row['label'] }}</td>
                                        <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $row['amount'], 2) }}</td>
                                    </tr>
                                @endforeach
                                @if ((float) ($summary['fees_receivable'] ?? 0) > 0)
                                    <tr>
                                        <td class="px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6">Fees receivable (outstanding)</td>
                                        <td class="px-4 py-3 text-right font-semibold text-amber-600 dark:text-amber-400">₹{{ number_format((float) $summary['fees_receivable'], 2) }}</td>
                                    </tr>
                                @endif
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Recent journal entries</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Fee receipts show as credit (money received). Late-fee accruals show debit and credit.</p>
            </div>
            <div class="divide-y divide-gray-100 dark:divide-white/10">
                @forelse ($this->getPresentedEntries() as $presented)
                    @php($entry = $presented['entry'])
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
                                            <th class="py-1 pr-3 font-semibold">Entry</th>
                                            <th class="py-1 pr-3 font-semibold">Side</th>
                                            <th class="py-1 font-semibold text-right">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($presented['lines'] as $line)
                                            <tr>
                                                <td class="py-1 pr-3 text-gray-800 dark:text-gray-200">
                                                    {{ $line->label }}
                                                    @if ($line->detail)
                                                        <span class="block text-[11px] text-gray-500 dark:text-gray-400">{{ $line->detail }}</span>
                                                    @endif
                                                </td>
                                                <td class="py-1 pr-3">
                                                    <span @class([
                                                        'inline-flex rounded-md px-2 py-0.5 text-[11px] font-semibold uppercase tracking-wide',
                                                        'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-400' => $line->side === 'credit',
                                                        'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-400' => $line->side === 'debit',
                                                    ])>
                                                        {{ $line->sideLabel }}
                                                    </span>
                                                </td>
                                                <td @class([
                                                    'py-1 text-right font-semibold',
                                                    'text-emerald-600 dark:text-emerald-400' => $line->side === 'credit',
                                                    'text-amber-600 dark:text-amber-400' => $line->side === 'debit',
                                                ])>
                                                    ₹{{ number_format($line->amount, 2) }}
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                    </div>
                @empty
                    <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No entries in this period.</p>
                @endforelse
            </div>
        </div>
    </div>
</x-filament-panels::page>

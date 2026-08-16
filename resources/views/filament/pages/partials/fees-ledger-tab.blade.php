<div class="space-y-6">
    <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period</p>
                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">
                    Same dates as Overview · {{ $summary['range_label'] ?? $this->periodLabel() }}
                </p>
            </div>
            <form wire:submit="applyLedgerFilters" class="flex flex-wrap items-end gap-4">
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
    </div>

    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Journal entries</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($ledgerSummary['entry_count'] ?? 0) }}</p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Total collected</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($ledgerSummary['total_collected'] ?? 0), 2) }}</p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cash collected</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($ledgerSummary['cash_collected'] ?? 0), 2) }}</p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Bank / UPI collected</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($ledgerSummary['bank_collected'] ?? 0), 2) }}</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-2">
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Collections summary</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Money received during the selected period</p>
            </div>
            @if (empty($ledgerSummary['collection_rows'] ?? []))
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No fee collections in this period.</p>
            @else
                <x-crm.responsive-table>
                    <table class="w-full min-w-[320px] text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-6">Mode</th>
                                <th class="px-4 py-3 font-semibold text-right">Credit (received)</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($ledgerSummary['collection_rows'] as $row)
                                <tr>
                                    <td class="crm-responsive-table__title px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6" data-label="">{{ $row['label'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-emerald-600 dark:text-emerald-400" data-label="Received">₹{{ number_format((float) $row['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </x-crm.responsive-table>
            @endif
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Income breakdown</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Fee income recognized in this period</p>
            </div>
            @if (empty($ledgerSummary['income_rows'] ?? []) && (float) ($ledgerSummary['fees_receivable'] ?? 0) <= 0)
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No fee income activity yet.</p>
            @else
                <x-crm.responsive-table>
                    <table class="w-full min-w-[320px] text-left text-sm">
                        <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-3 font-semibold sm:px-6">Category</th>
                                <th class="px-4 py-3 font-semibold text-right">Amount</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($ledgerSummary['income_rows'] as $row)
                                <tr>
                                    <td class="crm-responsive-table__title px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6" data-label="">
                                        {{ $row['label'] }}
                                        @if (str_contains(strtolower($row['label']), 'late fee'))
                                            <span class="mt-0.5 block text-[11px] font-normal text-amber-700 dark:text-amber-300">Booked as receivable — not money received yet</span>
                                        @endif
                                    </td>
                                    <td @class([
                                        'px-4 py-3 text-right font-semibold',
                                        'text-amber-600 dark:text-amber-400' => str_contains(strtolower($row['label']), 'late fee'),
                                        'text-emerald-600 dark:text-emerald-400' => ! str_contains(strtolower($row['label']), 'late fee'),
                                    ]) data-label="Amount">₹{{ number_format((float) $row['amount'], 2) }}</td>
                                </tr>
                            @endforeach
                            @if ((float) ($ledgerSummary['fees_receivable'] ?? 0) > 0)
                                <tr>
                                    <td class="crm-responsive-table__title px-4 py-3 font-medium text-gray-950 dark:text-white sm:px-6" data-label="">
                                        Fees receivable (outstanding)
                                        <span class="mt-0.5 block text-[11px] font-normal text-gray-500">Still to collect — includes tuition + accrued late fees</span>
                                    </td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-600 dark:text-amber-400" data-label="Amount">₹{{ number_format((float) $ledgerSummary['fees_receivable'], 2) }}</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </x-crm.responsive-table>
            @endif
        </div>
    </div>

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
                <div>
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Journal entries</h2>
                    <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                        @if ($ledgerEntryFilter === 'collections')
                            Money received and cancelled receipts. Late-fee accruals are hidden by default.
                        @elseif ($ledgerEntryFilter === 'late_fees')
                            Late fees booked as receivable — not cash collected.
                        @elseif ($ledgerEntryFilter === 'cancels')
                            Soft-cancelled receipts only.
                        @else
                            Full journal: collections, cancels, and late-fee accruals.
                        @endif
                        @if (($ledgerEntriesTotal ?? 0) > 0)
                            · {{ (int) $ledgerEntriesTotal }} entr{{ (int) $ledgerEntriesTotal === 1 ? 'y' : 'ies' }}
                        @endif
                    </p>
                </div>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->ledgerEntryFilterOptions() as $option)
                        <button
                            type="button"
                            wire:click="setLedgerEntryFilter('{{ $option['key'] }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-xs font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $ledgerEntryFilter === $option['key'],
                                'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' => $ledgerEntryFilter !== $option['key'],
                            ])
                            title="{{ $option['hint'] }}"
                        >
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>
            </div>
        </div>
        <div class="divide-y divide-gray-100 dark:divide-white/10">
            @forelse ($this->getPresentedEntries() as $presented)
                @php
                    $entry = $presented['entry'];
                    $isLateFee = in_array($entry->reference_type?->value, ['fee_penalty', 'fee_misc_charge'], true);
                    $isCancel = $entry->reference_type?->value === 'payment_cancellation';
                    $title = $isLateFee
                        ? \Illuminate\Support\Str::after($entry->description, 'Late fee accrued — ')
                        : $entry->description;
                @endphp
                <div class="px-4 py-4 sm:px-6">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <p class="min-w-0 break-words text-sm font-semibold text-gray-950 dark:text-white">{{ $title }}</p>
                                @if ($isLateFee)
                                    <span class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-amber-800 dark:bg-amber-500/15 dark:text-amber-300">Accrual</span>
                                @elseif ($isCancel)
                                    <span class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-800 dark:bg-red-500/15 dark:text-red-300">Cancelled</span>
                                @else
                                    <span class="inline-flex rounded-full bg-emerald-100 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300">Collection</span>
                                @endif
                            </div>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry->entry_date?->format('d M Y') }}
                                @if ($entry->postedBy)
                                    · {{ $entry->postedBy->name }}
                                @endif
                                @if ($isLateFee)
                                    · Not cash — penalty booked as receivable
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="mt-3 space-y-2">
                        @forelse ($presented['lines'] as $line)
                            @php
                                $isMoneyIn = $line->sideLabel === 'Money in';
                                $isMoneyOut = $line->sideLabel === 'Money out';
                            @endphp
                            <div class="flex flex-wrap items-start justify-between gap-x-4 gap-y-1 rounded-lg bg-gray-50 px-3 py-2 dark:bg-white/5">
                                <div class="min-w-0 flex-1">
                                    <p class="break-words text-xs font-medium text-gray-800 dark:text-gray-200">{{ $line->label }}</p>
                                    @if ($line->detail)
                                        <p class="break-words text-[11px] text-gray-500 dark:text-gray-400">{{ $line->detail }}</p>
                                    @endif
                                </div>
                                <p @class([
                                    'shrink-0 whitespace-nowrap text-xs font-semibold tabular-nums',
                                    'text-emerald-600 dark:text-emerald-400' => $isMoneyIn,
                                    'text-red-600 dark:text-red-400' => $isMoneyOut,
                                    'text-amber-600 dark:text-amber-400' => ! $isMoneyIn && ! $isMoneyOut,
                                ])>
                                    {{ $isMoneyOut ? '−' : '' }}₹{{ number_format($line->amount, 2) }}
                                </p>
                            </div>
                        @empty
                            <p class="text-xs text-gray-500 dark:text-gray-400">No amount lines recorded for this entry.</p>
                        @endforelse
                    </div>
                </div>
            @empty
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">
                    @if ($ledgerEntryFilter === 'collections')
                        No fee collections or cancels in this period. Switch to Late fees to see accruals.
                    @elseif ($ledgerEntryFilter === 'late_fees')
                        No late-fee accruals in this period.
                    @elseif ($ledgerEntryFilter === 'cancels')
                        No cancelled receipts in this period.
                    @else
                        No entries in this period.
                    @endif
                </p>
            @endforelse
        </div>
        @if (($ledgerEntriesLastPage ?? 1) > 1)
            <div class="flex flex-wrap items-center justify-between gap-2 border-t border-gray-100 px-4 py-3 text-xs text-gray-500 dark:border-white/10 sm:px-6">
                <p>
                    Page {{ $ledgerEntriesPage ?? 1 }} / {{ $ledgerEntriesLastPage }}
                    · {{ \App\Support\CrmPagination::PER_PAGE }} per page
                    · {{ (int) ($ledgerEntriesTotal ?? 0) }} total
                </p>
                <div class="flex gap-2">
                    <button type="button" wire:click="previousLedgerEntriesPage" @disabled(($ledgerEntriesPage ?? 1) <= 1) class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40 dark:ring-white/10">Prev</button>
                    <button type="button" wire:click="nextLedgerEntriesPage" @disabled(($ledgerEntriesPage ?? 1) >= ($ledgerEntriesLastPage ?? 1)) class="rounded-lg px-3 py-1.5 font-semibold ring-1 ring-gray-200 disabled:opacity-40 dark:ring-white/10">Next</button>
                </div>
            </div>
        @endif
    </div>
</div>

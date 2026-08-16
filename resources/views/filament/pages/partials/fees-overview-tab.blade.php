@php
    use App\Filament\Pages\MiscChargeAdjustmentRequestsPage;
    use App\Filament\Pages\PaymentCancellationRequestsPage;

    $summary = $summary ?? [];
    $discounts = $summary['discounts'] ?? [];
    $modes = $summary['payment_modes'] ?? [];
    $sevenDay = $summary['last_seven_days'] ?? [];
    $monthly = $summary['monthly'] ?? ['labels' => [], 'data' => [], 'max' => 1];
    $monthlyMax = max(1.0, (float) ($monthly['max'] ?? 1));
    $canSeeAdjustments = MiscChargeAdjustmentRequestsPage::canAccess();
    $canSeeCancels = PaymentCancellationRequestsPage::canAccess();
@endphp

<div class="space-y-6">
    <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="space-y-2">
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Period</p>
                <div class="flex flex-wrap gap-2">
                    @foreach ($this->rangePresetOptions() as $option)
                        <button
                            type="button"
                            wire:click="setRangePreset('{{ $option['key'] }}')"
                            @class([
                                'rounded-lg px-3 py-1.5 text-sm font-semibold transition',
                                'bg-primary-600 text-white shadow-sm' => $rangePreset === $option['key'],
                                'bg-white text-gray-700 ring-1 ring-gray-950/10 hover:bg-gray-50 dark:bg-gray-900 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/5' => $rangePreset !== $option['key'],
                            ])
                        >
                            {{ $option['label'] }}
                        </button>
                    @endforeach
                </div>
                <p class="text-xs text-gray-500 dark:text-gray-400">
                    Showing {{ $summary['range_label'] ?? $this->periodLabel() }} · Overview and Fee ledger use the same dates
                </p>
            </div>

            <form wire:submit="applyPeriodFilters" class="flex flex-wrap items-end gap-3">
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" for="fees-from">From</label>
                    <input
                        id="fees-from"
                        type="date"
                        wire:model="fromDate"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <div>
                    <label class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400" for="fees-to">To</label>
                    <input
                        id="fees-to"
                        type="date"
                        wire:model="toDate"
                        class="mt-1 block rounded-lg border-gray-300 text-sm shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-white/10 dark:bg-gray-900 dark:text-white"
                    />
                </div>
                <x-filament::button type="submit" size="sm">Apply</x-filament::button>
                <x-filament::button color="gray" wire:click="refreshDashboard" size="sm">
                    Refresh
                </x-filament::button>
            </form>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Collected in period</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) ($summary['collection_range'] ?? 0), 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ (int) ($summary['receipt_count'] ?? 0) }} receipt(s)</p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Average receipt</p>
            <p class="mt-1 text-2xl font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) ($summary['average_receipt'] ?? 0), 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Today ₹{{ number_format((float) ($summary['collection_today'] ?? 0), 0) }} · Month ₹{{ number_format((float) ($summary['collection_month'] ?? 0), 0) }}</p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Discounts given</p>
            <p class="mt-1 text-2xl font-bold text-violet-700 dark:text-violet-300">₹{{ number_format((float) ($discounts['combined_total'] ?? 0), 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ (int) ($discounts['combined_count'] ?? 0) }} approved
                @if ($canSeeAdjustments)
                    · <a href="{{ MiscChargeAdjustmentRequestsPage::getUrl() }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">History</a>
                @endif
            </p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Cancelled in period</p>
            <p class="mt-1 text-2xl font-bold text-rose-700 dark:text-rose-300">₹{{ number_format((float) ($summary['cancelled_total'] ?? 0), 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                {{ (int) ($summary['cancelled_count'] ?? 0) }} receipt(s)
                @if ($canSeeCancels)
                    · <a href="{{ PaymentCancellationRequestsPage::getUrl() }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Queue</a>
                @endif
            </p>
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending fees</p>
            <p class="mt-1 text-2xl font-bold text-amber-700 dark:text-amber-400">₹{{ number_format((float) ($summary['pending_fees_total'] ?? 0), 2) }}</p>
            @if ((float) ($summary['pending_penalties_total'] ?? 0) > 0)
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">+ ₹{{ number_format((float) $summary['pending_penalties_total'], 2) }} late fees</p>
            @else
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">As of now</p>
            @endif
        </div>
        <div class="fi-section rounded-xl px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Overdue</p>
            <p class="mt-1 text-2xl font-bold text-red-700 dark:text-red-400">₹{{ number_format((float) ($summary['overdue_amount'] ?? 0), 2) }}</p>
            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ (int) ($summary['overdue_students_count'] ?? 0) }} student(s) · {{ (int) ($summary['overdue_installment_count'] ?? 0) }} installment(s)</p>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 lg:col-span-1">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Last 7 days</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Active collections only</p>
            </div>
            <div class="flex items-end gap-2 px-4 py-5 sm:px-6" style="min-height: 10rem;">
                @forelse ($sevenDay as $col)
                    <div class="flex flex-1 flex-col items-center gap-1.5">
                        <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ $col['display'] }}</span>
                        <div class="flex h-28 w-full items-end justify-center rounded-md bg-gray-50 dark:bg-white/5">
                            <span
                                @class([
                                    'w-3/5 max-w-[1.75rem] rounded-t-md bg-emerald-500/80 dark:bg-emerald-400/70',
                                    'ring-2 ring-primary-500 ring-offset-1 dark:ring-offset-gray-900' => ! empty($col['is_today']),
                                ])
                                style="height: {{ max(4, (float) ($col['height'] ?? 0)) }}%"
                                title="₹{{ number_format((float) $col['amount'], 2) }}"
                            ></span>
                        </div>
                        <span @class([
                            'text-[11px] font-semibold',
                            'text-primary-600 dark:text-primary-400' => ! empty($col['is_today']),
                            'text-gray-500 dark:text-gray-400' => empty($col['is_today']),
                        ])>{{ $col['day'] }}</span>
                    </div>
                @empty
                    <p class="w-full py-8 text-center text-sm text-gray-500 dark:text-gray-400">No collection data yet.</p>
                @endforelse
            </div>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 lg:col-span-1">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Monthly trend</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Last 6 months</p>
            </div>
            <div class="flex items-end gap-2 px-4 py-5 sm:px-6" style="min-height: 10rem;">
                @forelse (($monthly['data'] ?? []) as $index => $amount)
                    @php
                        $height = max(4, round(((float) $amount / $monthlyMax) * 100, 1));
                        $label = $monthly['labels'][$index] ?? '';
                    @endphp
                    <div class="flex flex-1 flex-col items-center gap-1.5">
                        <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">
                            {{ (float) $amount > 0 ? '₹'.number_format((float) $amount / 1000, (float) $amount >= 10000 ? 0 : 1).'k' : '—' }}
                        </span>
                        <div class="flex h-28 w-full items-end justify-center rounded-md bg-gray-50 dark:bg-white/5">
                            <span
                                class="w-3/5 max-w-[1.75rem] rounded-t-md bg-primary-500/80 dark:bg-primary-400/70"
                                style="height: {{ $height }}%"
                                title="{{ $label }} · ₹{{ number_format((float) $amount, 2) }}"
                            ></span>
                        </div>
                        <span class="text-[10px] font-semibold text-gray-500 dark:text-gray-400">{{ \Illuminate\Support\Str::before($label, ' ') }}</span>
                    </div>
                @empty
                    <p class="w-full py-8 text-center text-sm text-gray-500 dark:text-gray-400">No monthly data yet.</p>
                @endforelse
            </div>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 lg:col-span-1">
            <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Modes in period</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">How money came in</p>
            </div>
            @if ($modes === [])
                <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No collections in this period.</p>
            @else
                <div class="space-y-3 px-4 py-4 sm:px-6">
                    @foreach ($modes as $mode)
                        <div>
                            <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                <span class="font-medium text-gray-800 dark:text-gray-200">{{ $mode['label'] }}</span>
                                <span class="font-semibold text-emerald-700 dark:text-emerald-300">₹{{ number_format((float) $mode['total'], 0) }}</span>
                            </div>
                            <div class="h-2 overflow-hidden rounded-full bg-gray-100 dark:bg-white/10">
                                <div class="h-full rounded-full bg-emerald-500/80" style="width: {{ max(2, (float) $mode['pct']) }}%"></div>
                            </div>
                            <p class="mt-1 text-[11px] text-gray-500 dark:text-gray-400">{{ (int) $mode['count'] }} receipt(s) · {{ number_format((float) $mode['pct'], 1) }}%</p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:px-6">
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Discount breakdown (period)</h2>
            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Main fee discounts and approved charge adjustments in the selected dates</p>
        </div>
        <div class="grid gap-3 p-4 sm:grid-cols-2 xl:grid-cols-4 sm:p-6">
            <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Main fee discounts</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ (int) ($discounts['tuition_discount_count'] ?? 0) }}</p>
                <p class="text-xs text-emerald-700 dark:text-emerald-300">₹{{ number_format((float) ($discounts['tuition_discount_total'] ?? 0), 0) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Charge discounts</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ (int) ($discounts['misc_discount_count'] ?? 0) }}</p>
                <p class="text-xs text-amber-700 dark:text-amber-300">₹{{ number_format((float) ($discounts['misc_discount_total'] ?? 0), 0) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Charge waive-offs</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ (int) ($discounts['misc_waive_count'] ?? 0) }}</p>
                <p class="text-xs text-violet-700 dark:text-violet-300">₹{{ number_format((float) ($discounts['misc_waive_total'] ?? 0), 0) }}</p>
            </div>
            <div class="rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Rejected requests</p>
                <p class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ (int) ($discounts['misc_rejected_count'] ?? 0) }}</p>
                <p class="text-xs text-red-700 dark:text-red-300">Not applied</p>
            </div>
        </div>
    </div>

    <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
        <div class="flex flex-col gap-2 border-b border-gray-100 px-4 py-4 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Defaulters</h2>
                <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">Students with overdue installment balances (as of now)</p>
            </div>
        </div>

        @if ($defaulters->isEmpty())
            <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400 sm:px-6">No overdue installments right now.</p>
        @else
            <x-crm.responsive-table>
                <table class="w-full min-w-[640px] text-left text-sm">
                    <thead class="border-b border-gray-100 text-xs uppercase tracking-wide text-gray-500 dark:border-white/10 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-3 font-semibold sm:px-6">Student</th>
                            <th class="px-4 py-3 font-semibold">Course</th>
                            <th class="px-4 py-3 font-semibold">Due</th>
                            <th class="px-4 py-3 font-semibold">Days late</th>
                            <th class="px-4 py-3 font-semibold text-right">Overdue</th>
                            <th class="crm-responsive-table__actions px-4 py-3 font-semibold text-right">Collect</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @foreach ($defaulters as $row)
                            <tr class="hover:bg-gray-50/80 dark:hover:bg-white/5">
                                <td class="crm-responsive-table__title px-4 py-3 sm:px-6" data-label="">
                                    <a href="{{ $row['profile_url'] }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                        {{ $row['student_name'] }}
                                    </a>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">
                                        {{ $row['enrollment_number'] ?? '—' }}
                                        @if ($row['mobile'])
                                            · {{ $row['mobile'] }}
                                        @endif
                                    </p>
                                </td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Course">{{ $row['course_name'] ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-700 dark:text-gray-300" data-label="Due">
                                    {{ $row['next_due_date'] ? \Illuminate\Support\Carbon::parse($row['next_due_date'])->format('d M Y') : '—' }}
                                </td>
                                <td class="px-4 py-3" data-label="Days late">
                                    <span class="inline-flex rounded-full bg-red-500/15 px-2 py-0.5 text-xs font-semibold text-red-800 dark:text-red-300">
                                        {{ $row['days_overdue'] }} day(s)
                                    </span>
                                </td>
                                <td class="px-4 py-3 text-right font-semibold text-red-700 dark:text-red-300" data-label="Overdue">
                                    ₹{{ number_format((float) $row['pending_amount'], 2) }}
                                </td>
                                <td class="crm-responsive-table__actions px-4 py-3 text-right" data-label="">
                                    <a
                                        href="{{ $row['profile_url'] }}"
                                        class="inline-flex rounded-lg bg-primary-600 px-2.5 py-1.5 text-xs font-bold text-white hover:bg-primary-500"
                                    >
                                        Collect
                                    </a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        @endif
    </div>
</div>

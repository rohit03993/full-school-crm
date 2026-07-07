@php
    use App\Enums\FeeMiscChargeKind;
    use App\Enums\FeeMiscChargeStatus;

    $enrollment = $record->activeEnrollment;
    $course = $enrollment?->course;
    $fees = $enrollment?->feeStructure;
    $miscCharges = $fees?->miscCharges ?? collect();
    $bundledMisc = $miscCharges->filter(fn ($c) => $c->kind === FeeMiscChargeKind::Bundled);
    $separateMisc = $miscCharges->filter(fn ($c) => $c->kind !== FeeMiscChargeKind::Bundled);
    $pendingPenalties = $penalties->filter(fn ($p) => $p->status === \App\Enums\FeePenaltyStatus::Pending);
@endphp

<div wire:init="loadFeesTab">
@if (! $feesTabLoaded)
    <p class="py-6 text-center text-sm text-gray-500 dark:text-gray-400">Loading fees…</p>
@elseif (! $enrollment)
    <div class="fi-section rounded-xl px-4 py-6 text-center text-sm text-gray-500 dark:text-gray-400">
        No active enrollment yet. Approve admission to activate fees.
    </div>
@elseif (! $fees)
    <div class="rounded-lg border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-950 dark:text-amber-200">
        <p class="font-semibold">Fee structure pending</p>
        <p class="mt-0.5 text-xs">Approve the admission to create the fee record.</p>
    </div>
@else
    @php
        $tuitionPending = (float) $fees->pending_amount;
        $miscTotal = $fees->separateMiscChargesTotal();
        $miscPaid = $fees->separateMiscChargesPaidTotal();
        $miscPending = $fees->separateMiscChargesPendingTotal();
        $penaltyPending = $pendingPenalties->sum(fn ($p) => (float) $p->penalty_amount);
        $collectible = (float) $fees->totalCollectiblePending();
        $tuitionPaid = (float) $fees->paid_amount;
        $netTuition = (float) $fees->net_fee;
        $courseFee = (float) $fees->course_fee;
        $discount = (float) $fees->discount_amount;
    @endphp

    <div class="space-y-3">
        {{-- Summary --}}
        <div class="fi-section overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="flex flex-col gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:flex-row sm:items-center sm:justify-between sm:px-5">
                <div class="min-w-0">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">
                        {{ \App\Support\StudentLabels::rollNumberLabel() }} {{ $enrollment->enrollment_number }}
                        <span class="text-gray-300 dark:text-gray-600">·</span>
                        {{ $course?->name ?? 'Course' }}
                    </p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                        Tuition: ₹{{ number_format($courseFee, 0) }}
                        @if ($discount > 0)
                            − ₹{{ number_format($discount, 0) }} discount
                        @endif
                        = <span class="font-semibold text-gray-800 dark:text-gray-200">₹{{ number_format($netTuition, 0) }} net</span>
                        @if ($fees->hasOnlineAllowancePlan())
                            · Cash ₹{{ number_format((float) $fees->planned_cash_amount, 0) }} / Online ₹{{ number_format((float) $fees->planned_online_amount, 0) }}
                        @endif
                    </p>
                </div>
                @if ($collectible > 0)
                    <div class="shrink-0 text-right">
                        <p class="text-[10px] font-semibold uppercase tracking-wide text-orange-700 dark:text-orange-300">Total due now</p>
                        <p class="text-xl font-bold text-orange-700 dark:text-orange-300">₹{{ number_format($collectible, 0) }}</p>
                    </div>
                @else
                    <span class="inline-flex shrink-0 rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-800 dark:text-emerald-300">All cleared</span>
                @endif
            </div>

            <div class="grid grid-cols-2 divide-x divide-y divide-gray-100 dark:divide-white/10 lg:grid-cols-4 lg:divide-y-0">
                <div class="px-4 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Tuition paid</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-700 dark:text-emerald-400">₹{{ number_format($tuitionPaid, 0) }}</p>
                    <p class="text-[11px] text-gray-500">of ₹{{ number_format($netTuition, 0) }}</p>
                </div>
                <div class="px-4 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Tuition pending</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-700 dark:text-amber-400">₹{{ number_format($tuitionPending, 0) }}</p>
                </div>
                <div class="px-4 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Misc charges</p>
                    <p class="mt-0.5 text-lg font-bold text-violet-700 dark:text-violet-400">₹{{ number_format($miscPending, 0) }} <span class="text-sm font-medium text-gray-500">due</span></p>
                    <p class="text-[11px] text-gray-500">₹{{ number_format($miscPaid, 0) }} paid of ₹{{ number_format($miscTotal, 0) }}</p>
                </div>
                <div class="px-4 py-3">
                    <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Late fees</p>
                    <p class="mt-0.5 text-lg font-bold {{ $penaltyPending > 0 ? 'text-red-700 dark:text-red-400' : 'text-gray-400' }}">
                        ₹{{ number_format($penaltyPending, 0) }}
                    </p>
                </div>
            </div>

            @if ($collectible > 0)
                <div class="border-t border-gray-100 bg-gray-50/80 px-4 py-2 text-xs text-gray-600 dark:border-white/10 dark:bg-white/[0.02] dark:text-gray-400 sm:px-5">
                    @if ($canCollectFees ?? false)
                        Use <strong class="text-gray-900 dark:text-white">Add Payment</strong> for tuition or misc.
                        Adjust fees via <strong class="text-gray-900 dark:text-white">Adjust Fees</strong>.
                    @else
                        Contact staff with fee collection permission to record payments.
                    @endif
                </div>
            @endif
        </div>

        @if ($fees->discountEntries->isNotEmpty() || $bundledMisc->isNotEmpty() || ($discount > 0 && $fees->discountSetBy))
            <details class="fi-section group rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <summary class="cursor-pointer list-none px-4 py-2.5 text-sm font-semibold text-gray-800 marker:content-none dark:text-gray-200 sm:px-5">
                    <span class="flex items-center justify-between gap-2">
                        Fee details & discount history
                        <span class="text-xs font-normal text-gray-500 group-open:hidden">Show</span>
                        <span class="hidden text-xs font-normal text-gray-500 group-open:inline">Hide</span>
                    </span>
                </summary>
                <div class="space-y-3 border-t border-gray-100 px-4 py-3 text-sm dark:border-white/10 sm:px-5">
                    @if ($discount > 0 && $fees->discountSetBy)
                        <p class="text-xs text-gray-500">Total discount ₹{{ number_format($discount, 2) }} · granted by {{ $fees->discountSetBy->name }}</p>
                    @endif
                    @if ($fees->discountEntries->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach ($fees->discountEntries as $entry)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-md bg-gray-50 px-2.5 py-1.5 text-xs dark:bg-white/5">
                                    <span @class([
                                        'font-semibold',
                                        'text-emerald-700 dark:text-emerald-400' => $entry->isIncrease(),
                                        'text-amber-700 dark:text-amber-300' => ! $entry->isIncrease(),
                                    ])>
                                        {{ $entry->isIncrease() ? '+' : '' }}₹{{ number_format((float) $entry->amount, 2) }}
                                    </span>
                                    <span class="text-gray-500">
                                        {{ $entry->created_at->format('d M Y') }}
                                        @if ($entry->grantedBy) · {{ $entry->grantedBy->name }} @endif
                                        · total ₹{{ number_format((float) $entry->total_after, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                    @if ($bundledMisc->isNotEmpty())
                        <div>
                            <p class="text-[10px] font-semibold uppercase tracking-wide text-gray-500">Included in net fee</p>
                            <ul class="mt-1 space-y-0.5 text-xs">
                                @foreach ($bundledMisc as $charge)
                                    <li class="flex justify-between gap-4">
                                        <span>{{ $charge->label }}</span>
                                        <span class="font-medium">₹{{ number_format((float) $charge->amount, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </details>
        @endif

        @if ($installments->isNotEmpty())
            <div class="fi-section overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-2 dark:border-white/10 sm:px-5">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Installments</h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-xs">
                        <thead class="bg-gray-50/80 text-[10px] uppercase tracking-wide text-gray-500 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-4 py-2 font-semibold sm:px-5">Label</th>
                                <th class="px-4 py-2 font-semibold">Due</th>
                                <th class="px-4 py-2 font-semibold text-right">Paid</th>
                                <th class="px-4 py-2 font-semibold text-right">Pending</th>
                                <th class="px-4 py-2 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($installments as $installment)
                                @php
                                    $status = $installment->statusLabel();
                                    $statusClass = match ($status) {
                                        'Paid' => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
                                        'Overdue' => 'bg-red-500/15 text-red-800 dark:text-red-300',
                                        'Partial' => 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
                                        default => 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]">
                                    <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white sm:px-5">{{ $installment->label }}</td>
                                    <td class="px-4 py-2.5 text-gray-600 dark:text-gray-400">{{ $installment->due_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-2.5 text-right text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $installment->paid_amount, 0) }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-gray-900 dark:text-white">₹{{ number_format((float) $installment->pending_amount, 0) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass }}">{{ $status }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($separateMisc->isNotEmpty())
            <div class="fi-section overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-2 dark:border-white/10 sm:px-5">
                    <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Misc charges <span class="font-normal text-gray-500">(pay separately)</span></h3>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[560px] text-left text-xs">
                        <thead class="bg-gray-50/80 text-[10px] uppercase tracking-wide text-gray-500 dark:bg-white/[0.03]">
                            <tr>
                                <th class="px-4 py-2 font-semibold sm:px-5">Charge</th>
                                <th class="px-4 py-2 font-semibold text-right">Total</th>
                                <th class="px-4 py-2 font-semibold text-right">Paid</th>
                                <th class="px-4 py-2 font-semibold text-right">Pending</th>
                                <th class="px-4 py-2 font-semibold">Status</th>
                                <th class="px-4 py-2 font-semibold text-right">Action</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @foreach ($separateMisc as $charge)
                                @php
                                    $statusLabel = $charge->status->label();
                                    $chargePaid = (float) $charge->paid_amount;
                                    $chargePending = $charge->pendingAmount();
                                    $statusClass = match ($charge->status) {
                                        FeeMiscChargeStatus::Paid => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
                                        FeeMiscChargeStatus::Partial => 'bg-sky-500/15 text-sky-800 dark:text-sky-300',
                                        FeeMiscChargeStatus::Cancelled => 'bg-gray-500/10 text-gray-600 dark:text-gray-400',
                                        default => 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
                                    };
                                @endphp
                                <tr class="hover:bg-gray-50/50 dark:hover:bg-white/[0.02]" wire:key="misc-{{ $charge->id }}">
                                    <td class="px-4 py-2.5 sm:px-5">
                                        <p class="font-medium text-gray-950 dark:text-white">{{ $charge->label }}</p>
                                        @if ($charge->due_date)
                                            <p class="text-[10px] text-gray-500">Due {{ $charge->due_date->format('d M Y') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right">₹{{ number_format((float) $charge->amount, 0) }}</td>
                                    <td class="px-4 py-2.5 text-right text-emerald-700 dark:text-emerald-400">₹{{ number_format($chargePaid, 0) }}</td>
                                    <td class="px-4 py-2.5 text-right font-semibold text-amber-700 dark:text-amber-300">₹{{ number_format($chargePending, 0) }}</td>
                                    <td class="px-4 py-2.5">
                                        <span class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-4 py-2.5 text-right">
                                        @if ($charge->isPayableSeparately())
                                            <button type="button" wire:click="openPayMiscCharge({{ $charge->id }})" class="text-[11px] font-semibold text-success-600 hover:underline dark:text-success-400">Pay</button>
                                            <button type="button" wire:click="cancelMiscCharge({{ $charge->id }})" wire:confirm="Cancel this charge?" class="ml-2 text-[11px] text-gray-400 hover:text-gray-600">Cancel</button>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($pendingPenalties->isNotEmpty())
            <div class="fi-section overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-2 dark:border-white/10 sm:px-5">
                    <h3 class="text-sm font-semibold text-red-700 dark:text-red-400">Late fees</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($pendingPenalties as $penalty)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-xs sm:px-5" wire:key="penalty-{{ $penalty->id }}">
                            <div class="min-w-0">
                                <span class="font-semibold text-red-700 dark:text-red-300">{{ $penalty->penalty_type->label() }}</span>
                                <span class="text-gray-500">
                                    · {{ $penalty->feeInstallment?->label ?? 'Installment' }}
                                    · {{ $penalty->days_late }}d late
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-red-700 dark:text-red-300">₹{{ number_format((float) $penalty->penalty_amount, 0) }}</span>
                                @if ($canWaivePenalty ?? false)
                                    <div class="relative" x-data="{ open: false, reason: '' }">
                                        <button type="button" @click="open = !open" class="text-[11px] font-semibold text-primary-600 hover:underline">Waive</button>
                                        <div x-show="open" x-cloak class="absolute z-10 mt-1 w-52 rounded-lg border bg-white p-2 shadow-lg dark:border-white/10 dark:bg-gray-900">
                                            <textarea x-model="reason" rows="2" class="w-full rounded border-gray-300 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Reason"></textarea>
                                            <button type="button" class="mt-1 text-[11px] font-semibold text-danger-600" @click="$wire.waivePenalty({{ $penalty->id }}, reason); open = false; reason = ''">Confirm</button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <div class="fi-section overflow-hidden rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-2 dark:border-white/10 sm:px-5">
                <h3 class="text-sm font-semibold text-gray-950 dark:text-white">Payment history</h3>
            </div>
            @if ($payments->isEmpty())
                <p class="px-4 py-4 text-center text-xs text-gray-500 sm:px-5">No payments yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($payments as $payment)
                        <div class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 text-xs sm:px-5">
                            <div class="min-w-0">
                                <span class="font-mono font-bold text-primary-600 dark:text-primary-400">{{ $payment->receipt_number }}</span>
                                <span class="text-gray-500">
                                    · {{ $payment->payment_date->format('d M Y') }}
                                    · {{ $payment->payment_mode->label() }}
                                    @if ($payment->feeMiscCharge) · {{ $payment->feeMiscCharge->label }} @endif
                                    @if ($payment->feeInstallment) · {{ $payment->feeInstallment->label }} @endif
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $payment->amount, 0) }}</span>
                                @if ($payment->hasReceiptPdf())
                                    <x-crm.media-preview-button
                                        :url="$payment->receiptPreviewUrl()"
                                        :download-url="$payment->receiptDownloadUrl()"
                                        :title="'Receipt · '.$payment->receipt_number"
                                        :is-pdf="true"
                                        label="PDF"
                                    />
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        @if (isset($feeStructureHistory) && $feeStructureHistory->isNotEmpty())
            <details class="fi-section rounded-xl ring-1 ring-gray-950/5 dark:ring-white/10">
                <summary class="cursor-pointer list-none px-4 py-2.5 text-sm font-semibold text-gray-800 marker:content-none dark:text-gray-200 sm:px-5">
                    Fee change history ({{ $feeStructureHistory->count() }})
                </summary>
                <div class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/10 dark:border-white/10">
                    @foreach ($feeStructureHistory as $entry)
                        <div class="px-4 py-2.5 text-xs sm:px-5">
                            <p class="font-semibold text-gray-900 dark:text-white">
                                Net ₹{{ number_format((float) $entry->old_net_fee, 0) }} → ₹{{ number_format((float) $entry->new_net_fee, 0) }}
                                <span class="font-normal text-gray-500">· {{ $entry->changed_at?->format('d M Y') }}</span>
                            </p>
                            @if ($entry->reason)
                                <p class="mt-0.5 text-gray-500">{{ $entry->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </details>
        @endif
    </div>
@endif
</div>

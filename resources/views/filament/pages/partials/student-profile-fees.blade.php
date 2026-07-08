@php
    use App\Enums\FeeMiscChargeKind;
    use App\Enums\FeeMiscChargeStatus;

    $enrollment = $record->activeEnrollment;
    $course = $enrollment?->course;
    $fees = $enrollment?->feeStructure;
    $miscCharges = $fees?->miscCharges ?? collect();
    $bundledMisc = $miscCharges->filter(fn ($c) => $c->kind === FeeMiscChargeKind::Bundled);
    $separateMisc = $miscCharges->filter(fn ($c) => $c->kind !== FeeMiscChargeKind::Bundled);
    $activeMisc = $separateMisc
        ->reject(fn ($c) => $c->status === FeeMiscChargeStatus::Cancelled)
        ->sortBy(fn ($c) => [
            $c->kind === FeeMiscChargeKind::LateFeePenalty ? 0 : 1,
            $c->due_date?->timestamp ?? PHP_INT_MAX,
            $c->id,
        ])
        ->values();
    $archivedMisc = $separateMisc->filter(fn ($c) => $c->status === FeeMiscChargeStatus::Cancelled);
    $lateFeePending = (float) $fees?->pendingPenaltiesTotal();
    $needsCashOnlineSplit = $fees
        && \App\Support\FeeSettings::onlineAllowanceGstEnabled()
        && ! $fees->hasOnlineAllowancePlan();
@endphp

<div wire:init="loadFeesTab">
@if (! $feesTabLoaded)
    <div class="flex items-center justify-center py-12">
        <div class="flex items-center gap-3 text-sm text-gray-500 dark:text-gray-400">
            <svg class="h-5 w-5 animate-spin text-primary-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
            </svg>
            Loading fees…
        </div>
    </div>
@elseif (! $enrollment)
    <div class="rounded-2xl border border-dashed border-gray-200 px-6 py-10 text-center dark:border-white/10">
        <p class="text-sm font-medium text-gray-700 dark:text-gray-300">No active enrollment</p>
        <p class="mt-1 text-xs text-gray-500">Approve admission to activate fees.</p>
    </div>
@elseif (! $fees)
    <div class="rounded-2xl border border-amber-200/80 bg-gradient-to-br from-amber-50 to-orange-50 px-5 py-4 dark:border-amber-500/20 dark:from-amber-950/40 dark:to-orange-950/20">
        <p class="font-semibold text-amber-900 dark:text-amber-100">Fee structure pending</p>
        <p class="mt-0.5 text-xs text-amber-800/80 dark:text-amber-200/80">Approve the admission to create the fee record.</p>
    </div>
@else
    @php
        $tuitionPending = (float) $fees->pending_amount;
        $miscTotal = $fees->separateMiscChargesTotal();
        $miscPaid = $fees->separateMiscChargesPaidTotal();
        $miscPending = $fees->separateMiscChargesPendingTotal();
        $penaltyPending = $lateFeePending;
        $collectible = (float) $fees->totalCollectiblePending();
        $tuitionPaid = (float) $fees->paid_amount;
        $netTuition = (float) $fees->net_fee;
        $courseFee = (float) $fees->course_fee;
        $discount = (float) $fees->discount_amount;
        $tuitionPct = $netTuition > 0 ? min(100, round($tuitionPaid / $netTuition * 100)) : 0;
    @endphp

    <div class="space-y-4">
        @if ($needsCashOnlineSplit)
            <div class="rounded-2xl border border-amber-200/80 bg-amber-50 px-4 py-3 dark:border-amber-500/20 dark:bg-amber-950/30">
                <p class="text-sm font-semibold text-amber-900 dark:text-amber-100">Cash / online split missing</p>
                <p class="mt-1 text-xs leading-relaxed text-amber-800/90 dark:text-amber-200/90">
                    GST on online overage is enabled institute-wide, but this student has no agreed split yet.
                    Use <span class="font-semibold">Adjust Fees → Cash / online</span> before collecting online or UPI tuition.
                </p>
            </div>
        @endif
        {{-- Financial overview --}}
        <div class="overflow-hidden rounded-2xl bg-gradient-to-br from-slate-900 via-slate-800 to-slate-900 text-white shadow-lg ring-1 ring-white/10">
            <div class="flex flex-col gap-4 p-5 sm:flex-row sm:items-start sm:justify-between">
                <div class="min-w-0 flex-1">
                    <p class="text-[11px] font-medium uppercase tracking-wider text-slate-400">
                        {{ \App\Support\StudentLabels::rollNumberLabel() }} {{ $enrollment->enrollment_number }}
                        <span class="text-slate-600">·</span>
                        {{ $course?->name ?? 'Course' }}
                    </p>
                    <p class="mt-2 text-2xl font-bold tracking-tight sm:text-3xl">
                        ₹{{ number_format($netTuition, 0) }}
                        <span class="text-base font-medium text-slate-400">net tuition</span>
                    </p>
                    @if ($discount > 0 || $fees->hasOnlineAllowancePlan())
                        <p class="mt-1 text-xs text-slate-400">
                            Course ₹{{ number_format($courseFee, 0) }}
                            @if ($discount > 0)
                                · Discount −₹{{ number_format($discount, 0) }}
                            @endif
                            @if ($fees->hasOnlineAllowancePlan())
                                · Cash ₹{{ number_format((float) $fees->planned_cash_amount, 0) }} / Online ₹{{ number_format((float) $fees->planned_online_amount, 0) }}
                            @endif
                        </p>
                    @endif

                    <div class="mt-4">
                        <div class="mb-1.5 flex items-center justify-between text-xs">
                            <span class="text-slate-400">Tuition collected</span>
                            <span class="font-semibold text-emerald-400">{{ $tuitionPct }}%</span>
                        </div>
                        <div class="h-2 overflow-hidden rounded-full bg-slate-700/80">
                            <div
                                class="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all duration-500"
                                style="width: {{ $tuitionPct }}%"
                            ></div>
                        </div>
                        <p class="mt-1.5 text-xs text-slate-400">
                            ₹{{ number_format($tuitionPaid, 0) }} paid · ₹{{ number_format($tuitionPending, 0) }} remaining
                        </p>
                    </div>
                </div>

                <div class="shrink-0 sm:text-right">
                    @if ($collectible > 0)
                        <p class="text-[11px] font-medium uppercase tracking-wider text-orange-300">Balance due</p>
                        <p class="mt-1 text-3xl font-bold text-orange-300">₹{{ number_format($collectible, 0) }}</p>
                        @if ($canCollectFees ?? false)
                            <p class="mt-2 max-w-[220px] text-[11px] leading-relaxed text-slate-400 sm:ml-auto">
                                Record payments with <span class="text-white">Add Payment</span>. Change plan via <span class="text-white">Adjust Fees</span>.
                            </p>
                        @endif
                    @else
                        <div class="inline-flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1.5 ring-1 ring-emerald-400/30">
                            <span class="h-2 w-2 rounded-full bg-emerald-400"></span>
                            <span class="text-sm font-semibold text-emerald-300">All cleared</span>
                        </div>
                    @endif
                </div>
            </div>

            <div class="grid grid-cols-2 border-t border-white/10 sm:grid-cols-4">
                <div class="border-white/10 px-4 py-3 sm:border-r">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Tuition paid</p>
                    <p class="mt-0.5 text-lg font-bold text-emerald-400">₹{{ number_format($tuitionPaid, 0) }}</p>
                </div>
                <div class="border-t border-white/10 px-4 py-3 sm:border-r sm:border-t-0">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Tuition due</p>
                    <p class="mt-0.5 text-lg font-bold text-amber-300">₹{{ number_format($tuitionPending, 0) }}</p>
                </div>
                <div class="border-white/10 px-4 py-3 sm:border-r">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Misc charges</p>
                    <p class="mt-0.5 text-lg font-bold text-violet-300">₹{{ number_format($miscPending, 0) }}</p>
                    <p class="text-[10px] text-slate-500">₹{{ number_format($miscPaid, 0) }} / ₹{{ number_format($miscTotal, 0) }}</p>
                </div>
                <div class="border-t border-white/10 px-4 py-3 sm:border-t-0">
                    <p class="text-[10px] font-medium uppercase tracking-wider text-slate-500">Late fees</p>
                    <p @class([
                        'mt-0.5 text-lg font-bold',
                        'text-red-400' => $penaltyPending > 0,
                        'text-slate-500' => $penaltyPending <= 0,
                    ])>₹{{ number_format($penaltyPending, 0) }}</p>
                </div>
            </div>
        </div>

        @if ($fees->discountEntries->isNotEmpty() || $bundledMisc->isNotEmpty() || ($discount > 0 && $fees->discountSetBy))
            <details class="group overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 px-5 py-3.5 marker:content-none">
                    <div class="flex items-center gap-3">
                        <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-primary-500/10 text-primary-600 dark:text-primary-400">
                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a2 2 0 002-2V5a2 2 0 00-2-2H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
                        </span>
                        <div>
                            <p class="text-sm font-semibold text-gray-900 dark:text-white">Fee breakdown & discounts</p>
                            <p class="text-xs text-gray-500">Course fee, concessions, bundled items</p>
                        </div>
                    </div>
                    <span class="text-xs text-gray-400 group-open:hidden">Expand</span>
                </summary>
                <div class="space-y-3 border-t border-gray-100 px-5 py-4 text-sm dark:border-white/10">
                    @if ($discount > 0 && $fees->discountSetBy)
                        <p class="text-xs text-gray-500">Total discount ₹{{ number_format($discount, 2) }} · granted by {{ $fees->discountSetBy->name }}</p>
                    @endif
                    @if ($fees->discountEntries->isNotEmpty())
                        <div class="space-y-1.5">
                            @foreach ($fees->discountEntries as $entry)
                                <div class="flex flex-wrap items-center justify-between gap-2 rounded-lg bg-gray-50 px-3 py-2 text-xs dark:bg-white/5">
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
                            <ul class="mt-2 space-y-1 text-xs">
                                @foreach ($bundledMisc as $charge)
                                    <li class="flex justify-between gap-4 rounded-md px-2 py-1 hover:bg-gray-50 dark:hover:bg-white/5">
                                        <span class="text-gray-700 dark:text-gray-300">{{ $charge->label }}</span>
                                        <span class="font-medium text-gray-900 dark:text-white">₹{{ number_format((float) $charge->amount, 2) }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                </div>
            </details>
        @endif

        @if ($installments->isNotEmpty())
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5 dark:border-white/10">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-blue-500/10 text-blue-600 dark:text-blue-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Payment schedule</h3>
                        <p class="text-xs text-gray-500">{{ $installments->count() }} installments · sorted by due date</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[540px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/60 text-[10px] uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/[0.02]">
                                <th class="px-5 py-2.5 font-semibold">Installment</th>
                                <th class="px-4 py-2.5 font-semibold">Due date</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Paid</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Balance</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($installments as $installment)
                                @php
                                    $status = $installment->statusLabel();
                                    $statusClass = match ($status) {
                                        'Paid' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                        'Overdue' => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                                        'Partial' => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
                                        default => 'bg-gray-100 text-gray-600 dark:bg-white/10 dark:text-gray-400',
                                    };
                                    $isPaid = $status === 'Paid';
                                @endphp
                                <tr @class([
                                    'transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.02]',
                                    'opacity-60' => $isPaid,
                                ])>
                                    <td class="px-5 py-3 font-medium text-gray-900 dark:text-white">{{ $installment->label }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $installment->due_date?->format('d M Y') ?? '—' }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $installment->paid_amount, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-900 dark:text-white">₹{{ number_format((float) $installment->pending_amount, 0) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $status }}</span>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($activeMisc->isNotEmpty())
            <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5 dark:border-white/10">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-violet-500/10 text-violet-600 dark:text-violet-400">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </span>
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Additional charges</h3>
                        <p class="text-xs text-gray-500">Hostel, materials, late fees, GST — paid separately via Add Payment</p>
                    </div>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[520px] text-left text-sm">
                        <thead>
                            <tr class="border-b border-gray-100 bg-gray-50/60 text-[10px] uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/[0.02]">
                                <th class="px-5 py-2.5 font-semibold">Charge</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Amount</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Paid</th>
                                <th class="px-4 py-2.5 font-semibold text-right">Due</th>
                                <th class="px-4 py-2.5 font-semibold">Status</th>
                                <th class="px-4 py-2.5 font-semibold text-right"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @foreach ($activeMisc as $charge)
                                @php
                                    $statusLabel = $charge->status->label();
                                    $chargePaid = (float) $charge->paid_amount;
                                    $chargePending = $charge->pendingAmount();
                                    $statusClass = match (true) {
                                        $charge->kind === FeeMiscChargeKind::LateFeePenalty => 'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300',
                                        $charge->kind === FeeMiscChargeKind::GstPenalty => 'bg-orange-100 text-orange-800 dark:bg-orange-500/15 dark:text-orange-300',
                                        $charge->status === FeeMiscChargeStatus::Paid => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300',
                                        $charge->status === FeeMiscChargeStatus::Partial => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
                                        default => 'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200',
                                    };
                                    $typeBadge = match ($charge->kind) {
                                        FeeMiscChargeKind::LateFeePenalty => 'Late fee',
                                        FeeMiscChargeKind::GstPenalty => 'GST',
                                        default => null,
                                    };
                                @endphp
                                <tr @class([
                                    'transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.02]',
                                    'bg-red-50/40 dark:bg-red-500/5' => $charge->kind === FeeMiscChargeKind::LateFeePenalty,
                                ]) wire:key="misc-{{ $charge->id }}">
                                    <td class="px-5 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($typeBadge)
                                                <span @class([
                                                    'inline-flex rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide',
                                                    'bg-red-200/80 text-red-900 dark:bg-red-500/20 dark:text-red-300' => $charge->kind === FeeMiscChargeKind::LateFeePenalty,
                                                    'bg-orange-200/80 text-orange-900 dark:bg-orange-500/20 dark:text-orange-300' => $charge->kind === FeeMiscChargeKind::GstPenalty,
                                                ])>{{ $typeBadge }}</span>
                                            @endif
                                            <p class="font-medium text-gray-900 dark:text-white">{{ $charge->label }}</p>
                                        </div>
                                        @if ($charge->due_date)
                                            <p class="mt-0.5 text-[11px] text-gray-500">Due {{ $charge->due_date->format('d M Y') }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-3 text-right text-gray-700 dark:text-gray-300">₹{{ number_format((float) $charge->amount, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-medium text-emerald-600 dark:text-emerald-400">₹{{ number_format($chargePaid, 0) }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-amber-700 dark:text-amber-300">₹{{ number_format($chargePending, 0) }}</td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-0.5 text-[11px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex flex-wrap items-center justify-end gap-2">
                                            @if ($charge->isPayableSeparately() && ($canCollectFees ?? false))
                                                <button
                                                    type="button"
                                                    wire:click="openPayMiscCharge({{ $charge->id }})"
                                                    class="inline-flex items-center gap-1 rounded-lg bg-primary-600 px-3 py-1.5 text-xs font-semibold text-white shadow-sm transition hover:bg-primary-500"
                                                >
                                                    Pay
                                                </button>
                                            @endif
                                            @if ($charge->isLateFeePenalty() && ($canWaivePenalty ?? false) && $charge->isPayableSeparately())
                                                <div x-data="{ open: false, reason: '' }">
                                                    <button
                                                        type="button"
                                                        @click="open = true; reason = ''"
                                                        class="inline-flex items-center gap-1 rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-900 transition hover:bg-amber-100 dark:border-amber-500/30 dark:bg-amber-500/10 dark:text-amber-200 dark:hover:bg-amber-500/20"
                                                    >
                                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>
                                                        Waive
                                                    </button>
                                                    <template x-teleport="body">
                                                        <div
                                                            x-show="open"
                                                            x-cloak
                                                            class="fixed inset-0 z-[200] flex items-center justify-center bg-gray-950/50 p-4 backdrop-blur-[1px]"
                                                            @keydown.escape.window="open = false"
                                                        >
                                                            <div
                                                                class="w-full max-w-md overflow-hidden rounded-2xl bg-white shadow-2xl ring-1 ring-gray-950/10 dark:bg-gray-900 dark:ring-white/10"
                                                                @click.outside="open = false"
                                                            >
                                                                <div class="border-b border-amber-100 bg-gradient-to-r from-amber-50 to-orange-50 px-5 py-4 dark:border-amber-500/20 dark:from-amber-950/40 dark:to-orange-950/20">
                                                                    <p class="text-[11px] font-bold uppercase tracking-wide text-amber-800 dark:text-amber-300">Waive late fee</p>
                                                                    <p class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">{{ $charge->label }}</p>
                                                                    <p class="mt-1 text-xs text-amber-900/80 dark:text-amber-200/80">Pending amount: <span class="font-bold">₹{{ number_format($chargePending, 2) }}</span></p>
                                                                </div>
                                                                <div class="space-y-3 px-5 py-4">
                                                                    <p class="text-xs leading-relaxed text-gray-600 dark:text-gray-400">
                                                                        Use when the institute agrees not to collect this penalty. The charge is removed from balance due and logged in the audit trail.
                                                                    </p>
                                                                    <div>
                                                                        <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Reason for waiver <span class="text-red-500">*</span></label>
                                                                        <textarea
                                                                            x-model="reason"
                                                                            rows="3"
                                                                            class="w-full rounded-xl border-gray-200 text-sm shadow-sm focus:border-amber-400 focus:ring-amber-400 dark:border-white/10 dark:bg-white/5 dark:text-white"
                                                                            placeholder="e.g. First-time delay, medical emergency, management approval"
                                                                        ></textarea>
                                                                        <p class="mt-1 text-[11px] text-gray-500" x-show="reason.trim().length > 0 && reason.trim().length < 3">Please enter at least 3 characters.</p>
                                                                    </div>
                                                                    <div class="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-xl border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50 dark:border-white/10 dark:text-gray-300 dark:hover:bg-white/5"
                                                                            @click="open = false"
                                                                        >
                                                                            Cancel
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="rounded-xl bg-amber-600 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-50"
                                                                            :disabled="reason.trim().length < 3"
                                                                            @click="$wire.waiveLateFeeMiscCharge({{ $charge->id }}, reason.trim()); open = false; reason = ''"
                                                                        >
                                                                            Waive ₹{{ number_format($chargePending, 0) }}
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </template>
                                                </div>
                                            @endif
                                            @if ($charge->canBeWaivedBySuperAdmin() && ($canWaiveMiscCharge ?? false))
                                                <div class="relative" x-data="{ open: false, reason: '' }">
                                                    <button type="button" @click="open = !open" class="rounded-lg border border-red-200 px-2.5 py-1.5 text-xs font-semibold text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10">Remove</button>
                                                    <div x-show="open" x-cloak class="absolute right-0 z-10 mt-1 w-60 rounded-xl border bg-white p-3 shadow-xl dark:border-white/10 dark:bg-gray-900">
                                                        <p class="mb-2 text-[11px] leading-relaxed text-gray-500">Super Admin only. Use when a charge was added by mistake. Requires a reason.</p>
                                                        <textarea x-model="reason" rows="2" class="w-full rounded-lg border-gray-200 text-xs dark:border-white/10 dark:bg-white/5" placeholder="Why is this charge being removed?"></textarea>
                                                        <button type="button" class="mt-2 w-full rounded-lg bg-red-600 py-1.5 text-xs font-semibold text-white hover:bg-red-500" @click="$wire.waiveMiscCharge({{ $charge->id }}, reason); open = false; reason = ''">Confirm removal</button>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        @if ($archivedMisc->isNotEmpty())
            <details class="overflow-hidden rounded-2xl bg-gray-50 ring-1 ring-gray-200 dark:bg-gray-900/50 dark:ring-white/10">
                <summary class="cursor-pointer list-none px-5 py-3 text-xs text-gray-500 marker:content-none">
                    {{ $archivedMisc->count() }} archived charge{{ $archivedMisc->count() === 1 ? '' : 's' }} (historical)
                </summary>
                <div class="divide-y divide-gray-200 border-t border-gray-200 dark:divide-white/10 dark:border-white/10">
                    @foreach ($archivedMisc as $charge)
                        <div class="flex items-center justify-between gap-4 px-5 py-2.5 text-xs text-gray-500">
                            <span>{{ $charge->label }}</span>
                            <span>₹{{ number_format((float) $charge->amount, 0) }} · Cancelled</span>
                        </div>
                    @endforeach
                </div>
            </details>
        @endif

        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="flex items-center gap-3 border-b border-gray-100 px-5 py-3.5 dark:border-white/10">
                <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-500/10 text-emerald-600 dark:text-emerald-400">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                </span>
                <div>
                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">Payment history</h3>
                    <p class="text-xs text-gray-500">{{ $payments->count() }} receipt{{ $payments->count() === 1 ? '' : 's' }} on record</p>
                </div>
            </div>
            @if ($payments->isEmpty())
                <p class="px-5 py-8 text-center text-sm text-gray-500">No payments recorded yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($payments as $payment)
                        @php
                            $modeLabel = $payment->payment_mode->label();
                            $modeClass = match (true) {
                                str_contains(strtolower($modeLabel), 'cash') => 'bg-lime-100 text-lime-800 dark:bg-lime-500/15 dark:text-lime-300',
                                str_contains(strtolower($modeLabel), 'online') || str_contains(strtolower($modeLabel), 'upi') => 'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-300',
                                default => 'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300',
                            };
                            $description = $payment->feeMiscCharge?->label ?? $payment->feeInstallment?->label;
                        @endphp
                        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3 transition-colors hover:bg-gray-50/80 dark:hover:bg-white/[0.02]">
                            <div class="flex min-w-0 flex-1 items-start gap-3">
                                <div class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-gray-100 dark:bg-white/5">
                                    <svg class="h-5 w-5 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $payment->receipt_number }}</span>
                                        <span class="inline-flex rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $modeClass }}">{{ $modeLabel }}</span>
                                    </div>
                                    <p class="mt-0.5 text-xs text-gray-500">
                                        {{ $payment->payment_date->format('d M Y') }}
                                        @if ($description)
                                            · {{ $description }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="text-base font-bold text-emerald-600 dark:text-emerald-400">₹{{ number_format((float) $payment->amount, 0) }}</span>
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
            <details class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <summary class="cursor-pointer list-none px-5 py-3.5 text-sm font-semibold text-gray-800 marker:content-none dark:text-gray-200">
                    Fee change history ({{ $feeStructureHistory->count() }})
                </summary>
                <div class="divide-y divide-gray-100 border-t border-gray-100 dark:divide-white/10 dark:border-white/10">
                    @foreach ($feeStructureHistory as $entry)
                        <div class="px-5 py-3 text-xs">
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

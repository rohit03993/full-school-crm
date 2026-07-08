@if ($enrollment && $fees)
    @php
        $netFee = (float) $fees->net_fee;
        $paidAmount = (float) $fees->paid_amount;
        $tuitionPending = (float) $fees->pending_amount;
        $miscPending = (float) $fees->separateMiscChargesPendingTotal();
        $collectiblePending = (float) $fees->totalCollectiblePending();
        $installments = $installments ?? collect();
        $miscCharges = ($miscCharges ?? collect())
            ->filter(fn ($charge) => $charge->isSeparateCharge() && $charge->status !== \App\Enums\FeeMiscChargeStatus::Cancelled)
            ->sortBy(fn ($charge) => [
                $charge->kind === \App\Enums\FeeMiscChargeKind::LateFeePenalty ? 0 : 1,
                $charge->due_date?->timestamp ?? PHP_INT_MAX,
                $charge->id,
            ])
            ->values();
        $payableMisc = $miscCharges->filter(fn ($charge) => $charge->pendingAmount() > 0);
    @endphp

    <div class="space-y-4 lg:grid lg:grid-cols-2 lg:items-start lg:gap-6 lg:space-y-0">
    <section class="portal-card p-4 sm:p-5">
        <div>
            <h2 class="font-display text-lg font-bold text-navy-900">Fee summary</h2>
            <p class="mt-0.5 text-sm text-navy-500">Your course fees and payment status</p>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-3 sm:gap-3">
            <div class="rounded-xl bg-navy-50 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-navy-500">Course fee</p>
                <p class="mt-1 text-base font-bold text-navy-900">₹{{ number_format((float) $fees->course_fee, 0) }}</p>
            </div>
            @if ((float) $fees->discount_amount > 0)
                <div class="rounded-xl bg-sky-50 p-3">
                    <p class="text-[10px] font-bold uppercase tracking-wide text-sky-700">Discount</p>
                    <p class="mt-1 text-base font-bold text-sky-900">−₹{{ number_format((float) $fees->discount_amount, 0) }}</p>
                </div>
            @endif
            <div class="rounded-xl bg-navy-50 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-navy-500">Net fee</p>
                <p class="mt-1 text-base font-bold text-navy-900">₹{{ number_format($netFee, 0) }}</p>
            </div>
            <div class="rounded-xl bg-emerald-50 p-3 ring-1 ring-emerald-100">
                <p class="text-[10px] font-bold uppercase tracking-wide text-emerald-700">Paid</p>
                <p class="mt-1 text-base font-bold text-emerald-800">₹{{ number_format($paidAmount, 0) }}</p>
            </div>
            <div @class([
                'col-span-2 rounded-xl p-3 sm:col-span-1',
                'bg-amber-50 ring-1 ring-amber-200' => $collectiblePending > 0,
                'bg-emerald-50 ring-1 ring-emerald-100' => $collectiblePending <= 0,
            ])>
                <p @class([
                    'text-[10px] font-bold uppercase tracking-wide',
                    'text-amber-800' => $collectiblePending > 0,
                    'text-emerald-700' => $collectiblePending <= 0,
                ])>Total due</p>
                <p @class([
                    'mt-1 text-lg font-bold',
                    'text-amber-900' => $collectiblePending > 0,
                    'text-emerald-800' => $collectiblePending <= 0,
                ])>₹{{ number_format($collectiblePending, 0) }}</p>
                @if ($collectiblePending > 0 && $miscPending > 0)
                    <p class="mt-1 text-[10px] leading-snug text-amber-800/80">
                        Tuition ₹{{ number_format($tuitionPending, 0) }}
                        @if ($miscPending > 0)
                            · Other ₹{{ number_format($miscPending, 0) }}
                        @endif
                    </p>
                @endif
            </div>
        </div>

        @if ($payableMisc->isNotEmpty())
            <div class="mt-4 rounded-xl border border-navy-100 p-3">
                <p class="text-[10px] font-bold uppercase tracking-wide text-navy-500">Additional charges due</p>
                <ul class="mt-2 space-y-2 text-sm text-navy-700">
                    @foreach ($payableMisc as $charge)
                        @php
                            $chargePending = $charge->pendingAmount();
                            $chargePaid = (float) $charge->paid_amount;
                            $typeBadge = match ($charge->kind) {
                                \App\Enums\FeeMiscChargeKind::LateFeePenalty => 'Late fee',
                                \App\Enums\FeeMiscChargeKind::GstPenalty => 'GST',
                                default => null,
                            };
                        @endphp
                        <li class="flex items-start justify-between gap-3 rounded-lg bg-navy-50/70 px-2.5 py-2">
                            <div class="min-w-0">
                                <p class="font-medium text-navy-900">{{ $charge->label }}</p>
                                @if ($typeBadge)
                                    <p class="mt-0.5 text-[10px] font-semibold uppercase tracking-wide text-red-700">{{ $typeBadge }}</p>
                                @endif
                                @if ($chargePaid > 0)
                                    <p class="mt-0.5 text-xs text-navy-500">Paid ₹{{ number_format($chargePaid, 2) }} of ₹{{ number_format((float) $charge->amount, 2) }}</p>
                                @endif
                            </div>
                            <span class="shrink-0 font-semibold text-amber-900">₹{{ number_format($chargePending, 2) }}</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="portal-card overflow-hidden">
        <div class="border-b border-navy-100 px-4 py-3.5 sm:px-5">
            <h2 class="font-display text-lg font-bold text-navy-900">Installment schedule</h2>
            <p class="mt-0.5 text-sm text-navy-500">What is due and when</p>
        </div>

        @if ($installments->isEmpty())
            <div class="px-4 py-8 text-center sm:px-5">
                <p class="text-sm text-navy-600">Installment details will appear here once scheduled.</p>
            </div>
        @else
            <ul class="divide-y divide-navy-100">
                @foreach ($installments as $installment)
                    @php
                        $status = $installment->statusLabel();
                    @endphp
                    <li class="p-4 sm:p-5">
                        <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <p class="font-semibold text-navy-900">{{ $installment->label }}</p>
                                <p class="mt-0.5 text-sm text-navy-600">
                                    Due {{ $installment->due_date?->format('d M Y') ?? 'On enrollment' }}
                                    · Paid ₹{{ number_format((float) $installment->paid_amount, 2) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="text-sm font-bold text-navy-900">₹{{ number_format((float) $installment->pending_amount, 2) }} due</p>
                                <span @class([
                                    'rounded-full px-2.5 py-0.5 text-xs font-semibold',
                                    'bg-emerald-100 text-emerald-800' => $status === 'Paid',
                                    'bg-red-100 text-red-800' => $status === 'Overdue',
                                    'bg-amber-100 text-amber-900' => $status === 'Partial',
                                    'bg-navy-100 text-navy-700' => $status === 'Pending',
                                ])>{{ $status }}</span>
                            </div>
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
    </div>

    <section class="portal-card overflow-hidden mt-4">
        <div class="border-b border-navy-100 px-4 py-3.5 sm:px-5">
            <h2 class="font-display text-lg font-bold text-navy-900">Payment history</h2>
            <p class="mt-0.5 text-sm text-navy-500">{{ $payments->count() }} payment{{ $payments->count() === 1 ? '' : 's' }} on record</p>
        </div>

        @if ($payments->isEmpty())
            <div class="px-4 py-8 text-center sm:px-5">
                <p class="text-sm font-medium text-navy-700">No payments yet</p>
                <p class="mt-1 text-sm text-navy-500">Visit the institute office to pay your fees.</p>
            </div>
        @else
            <ul class="divide-y divide-navy-100">
                @foreach ($payments as $payment)
                    <li class="p-4 sm:p-5">
                        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-bold text-brand-700">{{ $payment->receipt_number }}</p>
                                <p class="mt-1 text-sm text-navy-600">
                                    {{ $payment->payment_date->format('d M Y') }} · {{ $payment->payment_mode->label() }}
                                    @if ($payment->feeMiscCharge)
                                        · {{ $payment->feeMiscCharge->label }}
                                    @elseif ($payment->feeInstallment)
                                        · {{ $payment->feeInstallment->label }}
                                    @endif
                                </p>
                                <p class="mt-1.5 text-xl font-bold text-emerald-700">₹{{ number_format((float) $payment->amount, 2) }}</p>
                            </div>
                            @if ($payment->hasReceiptPdf())
                                <a href="{{ $payment->portalReceiptDownloadUrl() }}"
                                   class="touch-manipulation inline-flex w-full items-center justify-center gap-2 rounded-xl border border-navy-200 bg-white px-4 py-2.5 text-sm font-semibold text-navy-800 shadow-sm transition hover:bg-navy-50 sm:w-auto">
                                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
                                    Receipt
                                </a>
                            @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </section>
@elseif ($student->activeEnrollment)
    <section class="portal-card p-5 text-sm leading-relaxed text-navy-600">
        Fee details are being set up. Contact the office for payment queries.
    </section>
@else
    <section class="portal-card p-5 text-sm leading-relaxed text-navy-600">
        Fee information will appear here after enrollment is complete.
    </section>
@endif

@if ($enrollment && $fees)
    @php
        $netFee = (float) $fees->net_fee;
        $paidAmount = (float) $fees->paid_amount;
        $pendingAmount = (float) $fees->pending_amount;
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
                'bg-amber-50 ring-1 ring-amber-200' => $pendingAmount > 0,
                'bg-emerald-50 ring-1 ring-emerald-100' => $pendingAmount <= 0,
            ])>
                <p @class([
                    'text-[10px] font-bold uppercase tracking-wide',
                    'text-amber-800' => $pendingAmount > 0,
                    'text-emerald-700' => $pendingAmount <= 0,
                ])>Pending</p>
                <p @class([
                    'mt-1 text-lg font-bold',
                    'text-amber-900' => $pendingAmount > 0,
                    'text-emerald-800' => $pendingAmount <= 0,
                ])>₹{{ number_format($pendingAmount, 0) }}</p>
            </div>
        </div>
    </section>

    <section class="portal-card overflow-hidden">
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
    </div>
@elseif ($student->activeEnrollment)
    <section class="portal-card p-5 text-sm leading-relaxed text-navy-600">
        Fee details are being set up. Contact the office for payment queries.
    </section>
@else
    <section class="portal-card p-5 text-sm leading-relaxed text-navy-600">
        Fee information will appear here after enrollment is complete.
    </section>
@endif

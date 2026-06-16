@php
    $enrollment = $record->activeEnrollment;
    $course = $enrollment?->course;
    $fees = $enrollment?->feeStructure;
@endphp

<div wire:init="loadFeesTab">
@if (! $feesTabLoaded)
    <p class="text-sm text-gray-500 dark:text-gray-400">Loading fees…</p>
@elseif (! $enrollment)
    <div class="fi-section rounded-xl px-4 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10 sm:px-6">
        <p class="text-sm text-gray-500 dark:text-gray-400">No active enrollment yet. Approve admission to activate fees.</p>
    </div>
@elseif (! $fees)
    <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-4 text-sm text-amber-950 dark:text-amber-200 sm:px-5">
        <p class="font-semibold">Fee structure pending</p>
        <p class="mt-1">Approve the admission to create the fee record.</p>
    </div>
@else
    <div class="space-y-4">
        <div class="rounded-xl border-2 border-primary-500/40 bg-gradient-to-r from-primary-500/10 to-amber-500/5 px-4 py-5 sm:px-6">
            <p class="text-[10px] font-bold uppercase tracking-widest text-primary-700 dark:text-primary-400">Fee structure</p>
            <h3 class="mt-1 text-lg font-bold text-gray-950 dark:text-white">{{ $enrollment->enrollment_number }}</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $course?->name ?? 'Course' }} · {{ $course?->duration_label }}</p>
        </div>

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6 sm:py-4">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Fee breakdown</h3>
            </div>
            <dl class="grid gap-4 px-4 py-4 text-sm sm:grid-cols-2 lg:grid-cols-3 sm:px-6 sm:pb-6">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Course fee</dt>
                    <dd class="mt-0.5 text-lg font-bold text-gray-950 dark:text-white">₹{{ number_format((float) $fees->course_fee, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Discount</dt>
                    <dd class="mt-0.5 font-semibold text-gray-950 dark:text-white">₹{{ number_format((float) $fees->discount_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Net fee</dt>
                    <dd class="mt-0.5 text-lg font-bold text-primary-600 dark:text-primary-400">₹{{ number_format((float) $fees->net_fee, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Paid</dt>
                    <dd class="mt-0.5 font-semibold text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $fees->paid_amount, 2) }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Pending</dt>
                    <dd class="mt-0.5 text-lg font-semibold text-amber-700 dark:text-amber-400">₹{{ number_format((float) $fees->pending_amount, 2) }}</dd>
                </div>
            </dl>
        </div>

        @if ((float) $fees->pending_amount > 0)
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-950 dark:text-emerald-200 sm:px-5">
                <p class="font-semibold">Collect fee</p>
                <p class="mt-1">Use <strong>Add Payment</strong> in the page header to record cash, online or UPI payments.</p>
            </div>
        @endif

        <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                <h3 class="text-base font-semibold text-gray-950 dark:text-white">Payment history</h3>
            </div>
            @if ($payments->isEmpty())
                <p class="px-4 py-6 text-sm text-gray-500 sm:px-6 dark:text-gray-400">No payments recorded yet.</p>
            @else
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($payments as $payment)
                        <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div class="min-w-0">
                                <p class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $payment->receipt_number }}</p>
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $payment->payment_date->format('d M Y') }} · {{ $payment->payment_mode->label() }}
                                    @if ($payment->voucher_number) · Voucher {{ $payment->voucher_number }} @endif
                                    @if ($payment->transaction_id) · Txn {{ $payment->transaction_id }} @endif
                                    @if ($payment->utr_number) · UTR {{ $payment->utr_number }} @endif
                                </p>
                                <p class="text-xs text-gray-400">
                                    Collected by {{ $payment->addedBy?->staffCollectorLabel() ?? 'Staff' }}
                                </p>
                            </div>
                            <div class="flex flex-wrap items-center justify-end gap-2">
                                <p class="text-lg font-bold text-emerald-700 dark:text-emerald-400">₹{{ number_format((float) $payment->amount, 2) }}</p>
                                @if ($payment->hasReceiptPdf())
                                    <x-crm.media-preview-button
                                        :url="$payment->receiptPreviewUrl()"
                                        :download-url="$payment->receiptDownloadUrl()"
                                        :title="'Receipt · '.$payment->receipt_number"
                                        :is-pdf="true"
                                        label="Receipt"
                                    />
                                @endif
                                @if ($payment->isProofPreviewable())
                                    <x-crm.media-preview-button
                                        :url="$payment->proofPreviewUrl()"
                                        :title="'Payment proof · '.$payment->receipt_number"
                                        :is-pdf="$payment->isProofPdf()"
                                        label="View proof"
                                    />
                                @else
                                    <a href="{{ $payment->proofDownloadUrl() }}" class="text-xs font-semibold text-gray-600 hover:underline dark:text-gray-400">Download proof</a>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </div>
@endif
</div>

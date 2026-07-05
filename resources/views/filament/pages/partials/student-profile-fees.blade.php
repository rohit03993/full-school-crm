@php
    $enrollment = $record->activeEnrollment;
    $course = $enrollment?->course;
    $fees = $enrollment?->feeStructure;
    $miscCharges = $fees?->miscCharges ?? collect();
    $pendingPenalties = $penalties->filter(fn ($p) => $p->status === \App\Enums\FeePenaltyStatus::Pending);
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
            <p class="text-[10px] font-bold uppercase tracking-widest text-primary-700 dark:text-primary-400">{{ \App\Support\StudentLabels::rollNumberLabel() }}</p>
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
                    @if ((float) $fees->discount_amount > 0)
                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                            @if ($fees->discountSetBy)
                                Granted by {{ $fees->discountSetBy->name }}.
                            @endif
                            Net fee ₹{{ number_format((float) $fees->net_fee, 2) }} is the amount due (course fee minus discount plus misc charges).
                        </p>
                    @endif
                </div>
                @if ($fees->discountEntries->isNotEmpty())
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Discount history</dt>
                        <dd class="mt-2 space-y-2">
                            @foreach ($fees->discountEntries as $entry)
                                <div class="flex flex-col gap-0.5 rounded-lg bg-gray-50 px-3 py-2 text-sm dark:bg-white/5 sm:flex-row sm:items-center sm:justify-between">
                                    <div>
                                        <span @class([
                                            'font-semibold',
                                            'text-emerald-700 dark:text-emerald-400' => $entry->isIncrease(),
                                            'text-amber-700 dark:text-amber-300' => ! $entry->isIncrease(),
                                        ])>
                                            {{ $entry->isIncrease() ? '+' : '' }}₹{{ number_format((float) $entry->amount, 2) }}
                                        </span>
                                        <span class="text-gray-600 dark:text-gray-400">
                                            · {{ $entry->created_at->format('d M Y') }}
                                            @if ($entry->grantedBy)
                                                · {{ $entry->grantedBy->name }}
                                            @endif
                                        </span>
                                        @if ($entry->reason)
                                            <p class="text-xs text-gray-500 dark:text-gray-400">{{ $entry->reason }}</p>
                                        @endif
                                    </div>
                                    <span class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                        Total discount ₹{{ number_format((float) $entry->total_after, 2) }}
                                    </span>
                                </div>
                            @endforeach
                        </dd>
                    </div>
                @endif
                @if ($miscCharges->isNotEmpty())
                    <div class="sm:col-span-2 lg:col-span-3">
                        <dt class="text-xs font-medium uppercase tracking-wide text-gray-500 dark:text-gray-400">Miscellaneous</dt>
                        <dd class="mt-1 space-y-1">
                            @foreach ($miscCharges as $charge)
                                <div class="flex justify-between gap-4 text-sm">
                                    <span>{{ $charge->label }}</span>
                                    <span class="font-medium">₹{{ number_format((float) $charge->amount, 2) }}</span>
                                </div>
                            @endforeach
                        </dd>
                    </div>
                @endif
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

        @if ($installments->isNotEmpty())
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Installment schedule</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($installments as $installment)
                        @php
                            $status = $installment->statusLabel();
                            $statusClass = match ($status) {
                                'Paid' => 'bg-emerald-500/15 text-emerald-800 dark:text-emerald-300',
                                'Overdue' => 'bg-red-500/15 text-red-800 dark:text-red-300',
                                'Partial' => 'bg-amber-500/15 text-amber-900 dark:text-amber-200',
                                default => 'bg-gray-500/10 text-gray-700 dark:text-gray-300',
                            };
                        @endphp
                        <div class="flex flex-col gap-2 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                            <div>
                                <p class="font-semibold text-gray-950 dark:text-white">{{ $installment->label }}</p>
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                    Due {{ $installment->due_date?->format('d M Y') ?? 'On enrollment' }}
                                    · Paid ₹{{ number_format((float) $installment->paid_amount, 2) }}
                                </p>
                            </div>
                            <div class="flex items-center gap-3">
                                <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                    ₹{{ number_format((float) $installment->pending_amount, 2) }} pending
                                </p>
                                <span class="inline-flex rounded-full px-2.5 py-0.5 text-xs font-semibold {{ $statusClass }}">
                                    {{ $status }}
                                </span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @if ($pendingPenalties->isNotEmpty())
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Late fees</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($pendingPenalties as $penalty)
                        <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6" wire:key="penalty-{{ $penalty->id }}">
                            <div>
                                <p class="font-semibold text-red-700 dark:text-red-300">{{ $penalty->penalty_type->label() }}</p>
                                <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $penalty->feeInstallment?->label ?? 'Installment' }}
                                    · {{ $penalty->days_late }} day(s) late
                                    · {{ $penalty->description }}
                                </p>
                            </div>
                            <div class="flex flex-col items-end gap-2 sm:flex-row sm:items-center">
                                <p class="text-lg font-bold text-red-700 dark:text-red-300">₹{{ number_format((float) $penalty->penalty_amount, 2) }}</p>
                                @if ($canWaivePenalty ?? false)
                                    <div class="w-full sm:w-auto" x-data="{ open: false, reason: '' }">
                                        <button type="button" @click="open = !open" class="text-xs font-semibold text-primary-600 hover:underline dark:text-primary-400">
                                            Waive
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2 w-full min-w-[220px] rounded-lg border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
                                            <textarea x-model="reason" rows="2" class="w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Reason for waiver"></textarea>
                                            <button
                                                type="button"
                                                class="mt-2 text-xs font-semibold text-danger-600 hover:underline"
                                                @click="$wire.waivePenalty({{ $penalty->id }}, reason); open = false; reason = ''"
                                            >
                                                Confirm waive
                                            </button>
                                        </div>
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
                <p class="border-t border-gray-100 px-4 py-3 text-xs text-gray-500 dark:border-white/10 sm:px-6">
                    Late fees are calculated daily after {{ config('fees.late_fee.grace_days') }} day grace period.
                    @if (! ($canWaivePenalty ?? false))
                        Contact Super Admin to waive a late fee.
                    @endif
                </p>
            </div>
        @endif

        @if (isset($feeStructureHistory) && $feeStructureHistory->isNotEmpty())
            <div class="fi-section rounded-xl shadow-sm ring-1 ring-gray-950/5 dark:ring-white/10">
                <div class="border-b border-gray-100 px-4 py-3 dark:border-white/10 sm:px-6">
                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Fee change history</h3>
                </div>
                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($feeStructureHistory as $entry)
                        <div class="px-4 py-4 sm:px-6">
                            <p class="text-sm font-semibold text-gray-950 dark:text-white">
                                Net ₹{{ number_format((float) $entry->old_net_fee, 2) }}
                                → ₹{{ number_format((float) $entry->new_net_fee, 2) }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">· {{ $entry->changed_at?->format('d M Y') }}</span>
                            </p>
                            <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">
                                Course fee ₹{{ number_format((float) $entry->old_course_fee, 2) }} → ₹{{ number_format((float) $entry->new_course_fee, 2) }}
                                · Discount ₹{{ number_format((float) $entry->old_discount, 2) }} → ₹{{ number_format((float) $entry->new_discount, 2) }}
                                @if ($entry->changedBy)
                                    · {{ $entry->changedBy->name }}
                                @endif
                            </p>
                            @if ($entry->reason)
                                <p class="mt-1 text-sm text-gray-700 dark:text-gray-300">{{ $entry->reason }}</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        @php
            $collectible = (float) $fees->totalCollectiblePending();
        @endphp
        @if ($collectible > 0 && ($canCollectFees ?? false))
            <div class="rounded-xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-950 dark:text-emerald-200 sm:px-5">
                <p class="font-semibold">Collect payment</p>
                <p class="mt-1">
                    Outstanding total <strong>₹{{ number_format($collectible, 2) }}</strong>
                    @if ((float) $fees->pending_amount <= 0 && $pendingPenalties->isNotEmpty())
                        (late fees only — fee balance is cleared)
                    @endif
                    · Use <strong>Add Payment</strong> at the top of this page.
                </p>
            </div>
        @elseif ($collectible > 0)
            <div class="rounded-xl border border-gray-200 bg-gray-50 px-4 py-3 text-sm text-gray-700 dark:border-white/10 dark:bg-white/5 dark:text-gray-300 sm:px-5">
                <p class="font-semibold">Pending fees</p>
                <p class="mt-1">Only staff with fee collection permission can record payments. Contact your accountant or admin.</p>
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
                                    @if ($payment->feeInstallment) · {{ $payment->feeInstallment->label }} @endif
                                    @if ($payment->voucher_number) · Voucher {{ $payment->voucher_number }} @endif
                                    @if ($payment->transaction_id) · Txn {{ $payment->transaction_id }} @endif
                                    @if ($payment->utr_number) · UTR {{ $payment->utr_number }} @endif
                                </p>
                                <p class="text-xs text-gray-400">
                                    Collected by {{ $payment->addedBy?->staffCollectorLabel() ?? 'Staff' }}
                                    @if ($payment->shortfallSummary())
                                        <span class="block text-amber-700 dark:text-amber-300">{{ $payment->shortfallSummary() }}</span>
                                    @endif
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

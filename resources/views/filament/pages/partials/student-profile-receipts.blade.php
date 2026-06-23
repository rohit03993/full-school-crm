<div wire:init="loadReceiptsTab">
    @if (! $receiptsTabLoaded)
        <p class="text-sm text-gray-500 dark:text-gray-400">Loading receipts…</p>
    @elseif ($payments->isEmpty())
        <div class="rounded-xl border border-dashed border-gray-300 px-4 py-8 text-center dark:border-white/20">
            <p class="text-sm text-gray-600 dark:text-gray-400">No receipts yet.</p>
            <p class="mt-2 text-xs text-gray-500">Receipts are generated when you add a payment from the Fees tab.</p>
        </div>
    @else
        <div class="divide-y divide-gray-100 rounded-xl border border-gray-200 dark:divide-white/10 dark:border-white/10">
            @foreach ($payments as $payment)
                <div class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div class="min-w-0">
                        <p class="font-mono text-sm font-bold text-primary-600 dark:text-primary-400">{{ $payment->receipt_number }}</p>
                        <p class="mt-0.5 text-sm text-gray-600 dark:text-gray-400">
                            {{ $payment->payment_date->format('d M Y') }} · ₹{{ number_format((float) $payment->amount, 2) }} · {{ $payment->payment_mode->label() }}
                        </p>
                        <p class="text-xs text-gray-400">Collected by {{ $payment->addedBy?->staffCollectorLabel() ?? 'Staff' }}</p>
                    </div>
                    <div class="flex shrink-0 flex-wrap gap-2">
                        @if ($payment->hasReceiptPdf())
                            <x-crm.media-preview-button
                                :url="$payment->receiptPreviewUrl()"
                                :download-url="$payment->receiptDownloadUrl()"
                                :title="'Receipt · '.$payment->receipt_number"
                                :is-pdf="true"
                                label="View PDF"
                            />
                            <a
                                href="{{ $payment->receiptDownloadUrl() }}"
                                class="inline-flex items-center rounded-lg bg-primary-50 px-3 py-1.5 text-xs font-semibold text-primary-700 ring-1 ring-primary-200 hover:bg-primary-100 dark:bg-primary-500/10 dark:text-primary-300 dark:ring-primary-500/30"
                            >
                                Download PDF
                            </a>
                            @if ($canAdjustFees ?? false)
                                <x-filament::button
                                    wire:click="regenerateReceipt({{ $payment->id }})"
                                    size="sm"
                                    color="gray"
                                    outlined
                                >
                                    Regenerate
                                </x-filament::button>
                            @endif
                        @else
                            @if ($canCollectFees ?? false)
                            <x-filament::button
                                wire:click="generateReceipt({{ $payment->id }})"
                                size="sm"
                                color="gray"
                            >
                                Generate PDF
                            </x-filament::button>
                            @endif
                        @endif
                        @if ($payment->isProofPreviewable())
                            <x-crm.media-preview-button
                                :url="$payment->proofPreviewUrl()"
                                :title="'Payment proof · '.$payment->receipt_number"
                                :is-pdf="$payment->isProofPdf()"
                                label="View proof"
                                class="bg-gray-100 text-gray-700 ring-gray-200 hover:bg-gray-200 dark:bg-white/10 dark:text-gray-200 dark:ring-white/10"
                            />
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

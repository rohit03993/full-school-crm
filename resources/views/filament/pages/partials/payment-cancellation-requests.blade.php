<div class="space-y-6">
    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pending cancellation approval</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Approving reverses fee balances, marks the receipt Cancelled (not deleted), and posts a reversing ledger entry.
        </p>
    </div>

    @if ($requests->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-900 dark:text-white">No pending requests</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                When staff request cancellation of the latest payment on a student profile, it will appear here.
            </p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($requests as $request)
                @php
                    $payment = $request->payment;
                    $student = $payment?->student;
                    $course = $payment?->feeStructure?->enrollment?->course;
                    $target = $payment?->feeMiscCharge?->label
                        ?? $payment?->feeInstallment?->label
                        ?? 'Tuition';
                @endphp
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" wire:key="pay-cancel-req-{{ $request->id }}">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-red-700 dark:text-red-300">Cancel payment</p>
                                <p class="mt-1 font-mono text-base font-semibold text-gray-950 dark:text-white">{{ $payment?->receipt_number ?? '—' }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $student?->name ?? 'Student' }}
                                    @if ($course)
                                        · {{ $course->name }}
                                    @endif
                                    @if ($student)
                                        · <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id]) }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Open profile</a>
                                    @endif
                                </p>
                                <p class="mt-1 text-xs text-gray-500">{{ $target }} · {{ $payment?->payment_mode?->label() }} · {{ $payment?->payment_date?->format('d M Y') }}</p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-semibold text-red-700 dark:text-red-300">₹{{ number_format((float) ($payment?->amount ?? 0), 2) }}</p>
                                <p class="text-xs text-gray-500">Collected by {{ $payment?->addedBy?->name ?? '—' }}</p>
                            </div>
                        </div>
                    </div>
                    <div class="grid gap-4 px-5 py-4 sm:grid-cols-2">
                        <div>
                            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Reason from staff</p>
                            <p class="mt-1 text-sm text-gray-800 dark:text-gray-200">{{ $request->reason }}</p>
                            <p class="mt-2 text-xs text-gray-500">
                                Requested by {{ $request->requestedBy?->name ?? '—' }}
                                · {{ $request->created_at?->format('d M Y H:i') }}
                            </p>
                        </div>
                        <div x-data="{ notes: '' }">
                            <label class="mb-1.5 block text-xs font-semibold text-gray-700 dark:text-gray-300">Admin note (optional)</label>
                            <textarea x-model="notes" rows="2" class="w-full rounded-xl border-gray-200 text-sm dark:border-white/10 dark:bg-white/5" placeholder="Optional note for audit trail"></textarea>
                            <div class="mt-3 flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-500"
                                    @@click="$wire.approveRequest({{ $request->id }}, notes || null)"
                                >
                                    Approve cancel
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-red-200 px-4 py-2 text-sm font-semibold text-red-700 hover:bg-red-50 dark:border-red-500/30 dark:text-red-300 dark:hover:bg-red-500/10"
                                    @@click="$wire.rejectRequest({{ $request->id }}, notes || null)"
                                >
                                    Reject
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

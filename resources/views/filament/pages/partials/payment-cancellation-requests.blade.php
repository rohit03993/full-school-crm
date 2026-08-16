@php
    $summary = $summary ?? [];
    $history = $history ?? collect();
@endphp

<div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Pending review</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['pending_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">Awaiting Super Admin</p>
        </div>
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Approved cancels</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['approved_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">₹{{ number_format((float) ($summary['approved_total'] ?? 0), 0) }} reversed</p>
        </div>
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Rejected</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['rejected_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-red-700 dark:text-red-300">Kept as active payments</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 px-4 py-4 text-white shadow-sm ring-1 ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Decisions on record</p>
            <p class="mt-1 text-2xl font-bold">{{ (int) ($summary['reviewed_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-orange-300">Approved + rejected</p>
        </div>
    </div>

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

    <div class="pt-2">
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Cancellation history</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
            Approved and rejected cancellation decisions for {{ strtolower($feesLabel ?? 'fees') }} payments.
        </p>
    </div>

    @if ($history->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-900 dark:text-white">No cancellation decisions yet</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                After you approve or reject a request, it will appear here with the receipt and amount.
            </p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <x-crm.responsive-table>
                <table class="w-full min-w-[820px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70 text-[10px] uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/[0.02]">
                            <th class="px-5 py-3 font-semibold">Decided</th>
                            <th class="px-4 py-3 font-semibold">Receipt</th>
                            <th class="px-4 py-3 font-semibold">Student</th>
                            <th class="px-4 py-3 font-semibold text-right">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">Requested by</th>
                            <th class="px-4 py-3 font-semibold">Reviewed by</th>
                            <th class="px-4 py-3 font-semibold">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($history as $entry)
                            @php
                                $payment = $entry->payment;
                                $student = $payment?->student;
                                $isApproved = $entry->status === \App\Enums\PaymentCancellationRequestStatus::Approved;
                            @endphp
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="pay-cancel-hist-{{ $entry->id }}">
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-400" data-label="Decided">
                                    {{ ($entry->reviewed_at ?? $entry->updated_at)?->format('d M Y H:i') }}
                                </td>
                                <td class="px-4 py-3 font-mono text-xs font-semibold text-gray-900 dark:text-white" data-label="Receipt">
                                    {{ $payment?->receipt_number ?? '—' }}
                                </td>
                                <td class="px-4 py-3" data-label="Student">
                                    @if ($student)
                                        <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $student->name }}</a>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">—</span>
                                    @endif
                                </td>
                                <td @class([
                                    'px-4 py-3 text-right font-semibold',
                                    'text-emerald-700 dark:text-emerald-300' => $isApproved,
                                    'text-gray-700 dark:text-gray-300' => ! $isApproved,
                                ]) data-label="Amount">
                                    ₹{{ number_format((float) ($payment?->amount ?? 0), 2) }}
                                </td>
                                <td class="px-4 py-3" data-label="Status">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $isApproved,
                                        'bg-red-100 text-red-800 dark:bg-red-500/15 dark:text-red-300' => ! $isApproved,
                                    ])>{{ $entry->status->label() }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" data-label="Requested by">{{ $entry->requestedBy?->name ?? '—' }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400" data-label="Reviewed by">{{ $entry->reviewedBy?->name ?? '—' }}</td>
                                <td class="crm-responsive-table__wide px-4 py-3 text-gray-600 dark:text-gray-400" data-label="Reason">
                                    {{ $entry->reason ?: '—' }}
                                    @if (filled($entry->review_notes))
                                        <span class="mt-0.5 block text-[11px] text-gray-500">Admin: {{ $entry->review_notes }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-crm.responsive-table>
        </div>
    @endif
</div>

@php
    $summary = $summary ?? [];
@endphp

<div class="space-y-6">
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Main fee discounts</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['tuition_discount_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-emerald-700 dark:text-emerald-300">₹{{ number_format((float) ($summary['tuition_discount_total'] ?? 0), 0) }} reduced</p>
        </div>
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Charge discounts</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['misc_discount_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-amber-700 dark:text-amber-300">₹{{ number_format((float) ($summary['misc_discount_total'] ?? 0), 0) }} approved</p>
        </div>
        <div class="rounded-2xl bg-white px-4 py-4 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-gray-500">Charge waive-offs</p>
            <p class="mt-1 text-2xl font-bold text-gray-950 dark:text-white">{{ (int) ($summary['misc_waive_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-violet-700 dark:text-violet-300">₹{{ number_format((float) ($summary['misc_waive_total'] ?? 0), 0) }} waived</p>
        </div>
        <div class="rounded-2xl bg-gradient-to-br from-slate-900 to-slate-800 px-4 py-4 text-white shadow-sm ring-1 ring-white/10">
            <p class="text-[10px] font-bold uppercase tracking-wide text-slate-400">Total record</p>
            <p class="mt-1 text-2xl font-bold">{{ (int) ($summary['combined_count'] ?? 0) }}</p>
            <p class="mt-1 text-xs text-orange-300">₹{{ number_format((float) ($summary['combined_total'] ?? 0), 0) }} total reduction</p>
        </div>
    </div>

    <div>
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Pending approval</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Staff requests on additional charges (hostel, penalties, GST, etc.).</p>
    </div>

    @if ($requests->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-900 dark:text-white">No pending requests</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">When staff request a discount or waive-off on a student’s additional charge, it will appear here for approval.</p>
        </div>
    @else
        <div class="space-y-4">
            @foreach ($requests as $request)
                @php
                    $charge = $request->charge;
                    $student = $charge?->feeStructure?->enrollment?->student;
                    $pending = $charge?->pendingAmount() ?? 0;
                    $adjustAmount = $request->type === \App\Enums\FeeMiscChargeAdjustmentType::WaiveOff
                        ? $pending
                        : (float) $request->discount_amount;
                @endphp
                <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10" wire:key="adj-req-{{ $request->id }}">
                    <div class="border-b border-gray-100 px-5 py-4 dark:border-white/10">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <p class="text-xs font-bold uppercase tracking-wide text-amber-700 dark:text-amber-300">{{ $request->type->label() }}</p>
                                <p class="mt-1 text-base font-semibold text-gray-950 dark:text-white">{{ $charge?->label ?? 'Charge' }}</p>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                    {{ $student?->name ?? 'Student' }}
                                    @if ($student)
                                        · <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $student->id]) }}" class="font-semibold text-primary-600 hover:underline dark:text-primary-400">Open profile</a>
                                    @endif
                                </p>
                            </div>
                            <div class="text-right text-sm">
                                <p class="font-semibold text-amber-700 dark:text-amber-300">− ₹{{ number_format($adjustAmount, 2) }}</p>
                                <p class="text-xs text-gray-500">Pending: ₹{{ number_format($pending, 2) }}</p>
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
                                    Approve
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
        <h2 class="text-sm font-semibold text-gray-900 dark:text-white">Discount & waive-off record</h2>
        <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">Includes main {{ strtolower($feesLabel ?? 'fees') }} discounts and approved additional charge adjustments.</p>
    </div>

    @if (($history ?? collect())->isEmpty())
        <div class="rounded-2xl bg-white px-6 py-8 text-center shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <p class="text-sm font-medium text-gray-900 dark:text-white">No discount or waive-off recorded yet</p>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Main fee discounts from admission or Adjust Fees, and approved charge adjustments, will appear here.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
            <div class="overflow-x-auto">
                <table class="w-full min-w-[760px] text-left text-sm">
                    <thead>
                        <tr class="border-b border-gray-100 bg-gray-50/70 text-[10px] uppercase tracking-wider text-gray-500 dark:border-white/10 dark:bg-white/[0.02]">
                            <th class="px-5 py-3 font-semibold">Date</th>
                            <th class="px-4 py-3 font-semibold">Student</th>
                            <th class="px-4 py-3 font-semibold">Type</th>
                            <th class="px-4 py-3 font-semibold">Item</th>
                            <th class="px-4 py-3 font-semibold text-right">Amount</th>
                            <th class="px-4 py-3 font-semibold">Status</th>
                            <th class="px-4 py-3 font-semibold">By</th>
                            <th class="px-4 py-3 font-semibold">Reason</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                        @foreach ($history as $entry)
                            <tr class="hover:bg-gray-50/70 dark:hover:bg-white/[0.02]" wire:key="disc-hist-{{ $entry->source }}-{{ $entry->occurredAt->timestamp }}-{{ $entry->studentId }}-{{ $entry->label }}">
                                <td class="px-5 py-3 text-gray-600 dark:text-gray-400">{{ $entry->occurredAt->format('d M Y H:i') }}</td>
                                <td class="px-4 py-3">
                                    @if ($entry->studentId)
                                        <a href="{{ \App\Filament\Pages\StudentProfilePage::getUrl(['record' => $entry->studentId]) }}" class="font-medium text-primary-600 hover:underline dark:text-primary-400">{{ $entry->studentName }}</a>
                                    @else
                                        <span class="text-gray-700 dark:text-gray-300">{{ $entry->studentName }}</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $entry->kind === 'tuition_discount',
                                        'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200' => $entry->kind === 'misc_discount',
                                        'bg-violet-100 text-violet-800 dark:bg-violet-500/15 dark:text-violet-300' => $entry->kind === 'misc_waive_off',
                                    ])>{{ $entry->kindLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-800 dark:text-gray-200">{{ $entry->label }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-emerald-700 dark:text-emerald-300">− ₹{{ number_format($entry->amount, 2) }}</td>
                                <td class="px-4 py-3">
                                    <span @class([
                                        'inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-300' => $entry->status === 'approved',
                                        'bg-amber-100 text-amber-900 dark:bg-amber-500/15 dark:text-amber-200' => $entry->status === 'pending',
                                    ])>{{ $entry->statusLabel }}</span>
                                </td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $entry->actorName }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-400">{{ $entry->reason ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

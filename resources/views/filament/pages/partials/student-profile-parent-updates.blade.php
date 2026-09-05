@if (! $parentUpdatesTabLoaded)
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        Loading parent updates…
    </div>
@elseif ($parentFeeNotices->isEmpty())
    <div class="rounded-xl bg-gray-50 px-4 py-8 text-center text-sm text-gray-500 ring-1 ring-gray-200 dark:bg-white/5 dark:text-gray-400 dark:ring-white/10">
        No parent fee notices sent yet for this student.
        <span class="mt-1 block text-xs">Staff can send bulk pending-fee WhatsApp from WhatsApp → Parent fee notices.</span>
    </div>
@else
    <div class="space-y-3">
        <p class="text-sm text-gray-500 dark:text-gray-400">
            Manual pending-fee WhatsApp notices sent to the parent. These amounts are for messaging only — they are not the Fees ledger.
        </p>

        <div class="overflow-x-auto rounded-xl ring-1 ring-gray-200 dark:ring-white/10">
            <table class="min-w-full text-left text-sm">
                <thead class="bg-gray-50 text-[10px] font-semibold uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5">Sent</th>
                        <th class="px-4 py-2.5">Amount</th>
                        <th class="px-4 py-2.5">Due date</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5">Sent by</th>
                        <th class="px-4 py-2.5">Batch</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @foreach ($parentFeeNotices as $notice)
                        <tr class="bg-white dark:bg-gray-900">
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 whitespace-nowrap">
                                {{ $notice->sent_at?->format('d M Y, h:i A') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 font-medium text-gray-950 dark:text-white">
                                ₹ {{ number_format((float) $notice->amount, 2) }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $notice->due_date?->format('d M Y') ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5">
                                <x-crm.badge :tone="match ($notice->status?->value) {
                                    'sent' => 'success',
                                    'failed' => 'danger',
                                    default => 'gray',
                                }">
                                    {{ $notice->status?->label() ?? '—' }}
                                </x-crm.badge>
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $notice->sentBy?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300">
                                {{ $notice->batch?->name ?? '—' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

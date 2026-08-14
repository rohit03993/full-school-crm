<div class="mt-4 space-y-4">
    @if (! $ready)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Pick a <strong>class</strong> and <strong>date</strong> to see what each subject teacher has submitted.
        </div>
    @else
        @php($summary = $board['summary'])
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap gap-2 text-xs font-medium">
                <span class="rounded-full bg-amber-100 px-2.5 py-1 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200">Submitted: {{ $summary['submitted'] }}</span>
                <span class="rounded-full bg-sky-100 px-2.5 py-1 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200">Approved: {{ $summary['approved'] }}</span>
                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200">Sent: {{ $summary['sent'] }}</span>
                <span class="rounded-full bg-gray-100 px-2.5 py-1 text-gray-600 dark:bg-white/5 dark:text-gray-300">No homework: {{ $summary['missing'] }}</span>
            </div>
            <button
                type="button"
                wire:click="sendCombined"
                wire:loading.attr="disabled"
                wire:target="sendCombined"
                @disabled($summary['approved'] === 0 && $summary['sent'] === 0)
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="sendCombined">Send combined to parents</span>
                <span wire:loading wire:target="sendCombined">Sending…</span>
            </button>
        </div>

        @if ($summary['approved'] === 0 && $summary['sent'] === 0)
            <p class="text-xs text-gray-500 dark:text-gray-400">
                Approve at least one subject to enable sending. Only subjects with homework are added to the parent message ({{ $dateLabel }}).
            </p>
        @endif

        @if ($lastCombinedSendResult)
            @php
                $sendResult = $lastCombinedSendResult;
                $currency = $sendResult['currency'] ?? 'INR';
                $unitCost = (float) ($sendResult['unit_cost'] ?? 0);
                $totalCost = (float) ($sendResult['estimated_total_cost'] ?? 0);
            @endphp
            <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 dark:border-white/5">
                    <div>
                        <h3 class="text-sm font-semibold text-gray-900 dark:text-gray-100">Last combined send</h3>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $sendResult['sent'] ?? 0 }} sent · {{ $sendResult['failed'] ?? 0 }} failed · {{ $sendResult['skipped'] ?? 0 }} skipped
                        </p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-900 dark:text-gray-100">
                            Estimated total: {{ $currency }} {{ number_format($totalCost, 2) }}
                        </p>
                        <p class="text-xs text-gray-500 dark:text-gray-400">
                            {{ $currency }} {{ number_format($unitCost, 4) }} per successfully accepted message
                        </p>
                    </div>
                </div>

                <div class="max-h-72 overflow-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="sticky top-0 bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                            <tr>
                                <th class="px-4 py-2">Student</th>
                                <th class="px-4 py-2">Mobile number</th>
                                <th class="px-4 py-2">Result</th>
                                <th class="px-4 py-2 text-right">Estimated cost</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                            @forelse (($sendResult['recipients'] ?? []) as $recipient)
                                <tr>
                                    <td class="px-4 py-2.5 font-medium text-gray-900 dark:text-gray-100">{{ $recipient['name'] }}</td>
                                    <td class="px-4 py-2.5 font-mono text-gray-600 dark:text-gray-300">{{ $recipient['phone'] ?: '—' }}</td>
                                    <td class="px-4 py-2.5">
                                        <span @class([
                                            'inline-flex rounded-full px-2 py-0.5 text-xs font-medium',
                                            'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $recipient['status'] === 'sent',
                                            'bg-rose-100 text-rose-800 dark:bg-rose-500/15 dark:text-rose-200' => $recipient['status'] === 'failed',
                                            'bg-gray-100 text-gray-700 dark:bg-white/10 dark:text-gray-300' => $recipient['status'] === 'skipped',
                                        ])>{{ ucfirst($recipient['status']) }}</span>
                                        @if ($recipient['error'])
                                            <p class="mt-1 max-w-md text-xs text-rose-600 dark:text-rose-300">{{ $recipient['error'] }}</p>
                                        @endif
                                    </td>
                                    <td class="px-4 py-2.5 text-right text-gray-600 dark:text-gray-300">
                                        {{ $currency }} {{ number_format((float) $recipient['estimated_cost'], 4) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-5 text-center text-gray-500">No recipient details available.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <p class="border-t border-gray-100 px-4 py-2 text-xs text-gray-500 dark:border-white/5 dark:text-gray-400">
                    Costs are estimates from your configured Meta WhatsApp rates; final billing may differ.
                </p>
            </div>
        @endif

        <div class="overflow-hidden rounded-2xl border border-gray-200 bg-white dark:border-white/10 dark:bg-gray-900">
            <table class="w-full text-left text-sm">
                <thead class="bg-gray-50 text-xs uppercase tracking-wide text-gray-500 dark:bg-white/5 dark:text-gray-400">
                    <tr>
                        <th class="px-4 py-2.5">Subject</th>
                        <th class="px-4 py-2.5">Homework</th>
                        <th class="px-4 py-2.5">Teacher</th>
                        <th class="px-4 py-2.5">Status</th>
                        <th class="px-4 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                    @foreach ($board['subjects'] as $row)
                        <tr>
                            <td class="px-4 py-3 font-semibold text-gray-900 dark:text-gray-100">{{ $row['subject'] }}</td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">
                                @if ($row['assignment_id'])
                                    <span class="block max-w-xs truncate">{{ $row['title'] }}</span>
                                    <span class="mt-0.5 flex flex-wrap gap-2 text-xs">
                                        @if ($row['public_url'])
                                            <a href="{{ $row['public_url'] }}" target="_blank" class="text-primary-600 underline underline-offset-2 dark:text-primary-400">Preview link</a>
                                        @endif
                                        @if ($row['has_file'])
                                            <span class="text-gray-400">· file attached</span>
                                        @endif
                                    </span>
                                @else
                                    <span class="text-gray-400 dark:text-gray-500">No homework submitted</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $row['teacher'] ?? '—' }}</td>
                            <td class="px-4 py-3">
                                @if ($row['status_key'])
                                    <span @class([
                                        'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                        'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' => $row['status_key'] === 'submitted',
                                        'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200' => $row['status_key'] === 'approved',
                                        'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $row['status_key'] === 'sent',
                                    ])>{{ $row['status'] }}</span>
                                @else
                                    <span class="text-xs text-gray-400 dark:text-gray-500">—</span>
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex flex-wrap justify-end gap-2">
                                    @if ($row['status_key'] === 'submitted')
                                        <button
                                            type="button"
                                            wire:click="approve({{ $row['assignment_id'] }})"
                                            class="rounded-lg bg-sky-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-sky-500"
                                        >
                                            Approve
                                        </button>
                                    @endif
                                    @if ($row['assignment_id'] && $row['status_key'] !== 'sent')
                                        <button
                                            type="button"
                                            wire:click="remove({{ $row['assignment_id'] }})"
                                            wire:confirm="Remove this subject's homework for the day?"
                                            class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-white/10 dark:hover:bg-rose-500/10"
                                        >
                                            Remove
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>

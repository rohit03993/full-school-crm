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

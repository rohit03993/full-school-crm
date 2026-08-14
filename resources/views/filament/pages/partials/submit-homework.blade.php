<div class="mt-4 space-y-4">
    @if (! $ready)
        <div class="rounded-xl border border-dashed border-gray-300 bg-white px-4 py-8 text-center text-sm text-gray-500 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-400">
            Select a <strong>class</strong>. Subject auto-fills if you teach only one; otherwise pick the subject, then fill the homework and submit.
        </div>
    @else
        <div class="flex flex-wrap items-center justify-end gap-2">
            <button
                type="button"
                wire:click="submit"
                wire:loading.attr="disabled"
                wire:target="submit"
                class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-bold text-white hover:bg-primary-500 disabled:opacity-50"
            >
                <span wire:loading.remove wire:target="submit">Submit to admin</span>
                <span wire:loading wire:target="submit">Submitting…</span>
            </button>
        </div>
    @endif

    <div class="rounded-2xl border border-gray-200 bg-white p-4 dark:border-white/10 dark:bg-gray-900 sm:p-5">
        <h3 class="text-sm font-semibold text-gray-950 dark:text-white">
            Your homework for {{ $dateLabel }}
        </h3>

        @if ($submissions->isEmpty())
            <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                Nothing submitted yet for this class and date.
            </p>
        @else
            <ul class="mt-3 divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($submissions as $submission)
                    <li class="flex flex-wrap items-center justify-between gap-2 py-2.5">
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-gray-900 dark:text-gray-100">
                                {{ $submission->courseSubject?->displayLabel() ?? 'Subject' }}
                                <span class="font-normal text-gray-500 dark:text-gray-400">— {{ $submission->title }}</span>
                            </p>
                            <p class="mt-0.5 text-xs text-gray-500 dark:text-gray-400">
                                @php($status = $submission->status)
                                <span @class([
                                    'inline-flex items-center rounded-full px-2 py-0.5 font-medium',
                                    'bg-amber-100 text-amber-800 dark:bg-amber-500/15 dark:text-amber-200' => $status?->value === 'submitted',
                                    'bg-sky-100 text-sky-800 dark:bg-sky-500/15 dark:text-sky-200' => $status?->value === 'approved',
                                    'bg-emerald-100 text-emerald-800 dark:bg-emerald-500/15 dark:text-emerald-200' => $status?->value === 'sent',
                                ])>
                                    {{ $status?->label() }}
                                </span>
                                @if ($submission->hasFile())
                                    <span class="ml-1">· file attached</span>
                                @endif
                            </p>
                        </div>
                        @if ($status?->value !== 'sent')
                            <button
                                type="button"
                                wire:click="deleteSubmission({{ $submission->id }})"
                                wire:confirm="Remove this homework submission?"
                                class="rounded-lg border border-gray-200 px-3 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50 dark:border-white/10 dark:hover:bg-rose-500/10"
                            >
                                Remove
                            </button>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

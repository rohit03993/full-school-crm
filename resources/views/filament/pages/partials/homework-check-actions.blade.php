<div class="mt-4 space-y-4">
    <div class="flex flex-wrap gap-3">
        <button
            type="button"
            wire:click="markDone"
            wire:loading.attr="disabled"
            wire:target="markDone,markNotDone"
            class="inline-flex min-w-[9rem] items-center justify-center rounded-xl bg-emerald-600 px-5 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-emerald-500 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="markDone">Homework Done</span>
            <span wire:loading wire:target="markDone">Saving…</span>
        </button>
        <button
            type="button"
            wire:click="markNotDone"
            wire:loading.attr="disabled"
            wire:target="markDone,markNotDone"
            class="inline-flex min-w-[9rem] items-center justify-center rounded-xl bg-rose-600 px-5 py-3 text-sm font-bold uppercase tracking-wide text-white shadow-sm transition hover:bg-rose-500 disabled:opacity-50"
        >
            <span wire:loading.remove wire:target="markNotDone">Homework Not Done</span>
            <span wire:loading wire:target="markNotDone">Saving…</span>
        </button>
    </div>
    <p class="text-xs text-gray-500 dark:text-gray-400">
        Done saves only. Not Done saves and sends WhatsApp to the parent mobile when Automations → Homework not done is configured.
    </p>

    @if ($recent->isNotEmpty())
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white dark:border-gray-700 dark:bg-gray-900">
            <div class="border-b border-gray-100 px-4 py-3 text-sm font-semibold dark:border-gray-800">Recent marks (this class)</div>
            <div class="overflow-x-auto">
                <table class="w-full min-w-[40rem] text-left text-sm">
                    <thead class="bg-gray-50 text-[11px] font-bold uppercase tracking-wide text-gray-500 dark:bg-gray-800 dark:text-gray-400">
                        <tr>
                            <th class="px-4 py-2">Student</th>
                            <th class="px-4 py-2">Subject</th>
                            <th class="px-4 py-2">Topic</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">WhatsApp</th>
                            <th class="px-4 py-2">Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-gray-800">
                        @foreach ($recent as $row)
                            <tr wire:key="hw-check-{{ $row->id }}">
                                <td class="px-4 py-2 font-medium">{{ $row->student?->name }}</td>
                                <td class="px-4 py-2">{{ $row->subject_name }}</td>
                                <td class="px-4 py-2 text-gray-600 dark:text-gray-300">{{ \Illuminate\Support\Str::limit($row->topic, 40) }}</td>
                                <td class="px-4 py-2">{{ $row->status?->label() }}</td>
                                <td class="px-4 py-2 text-gray-500">{{ $row->notify_status?->label() }}</td>
                                <td class="px-4 py-2 text-xs text-gray-500">{{ $row->created_at?->format('d M H:i') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

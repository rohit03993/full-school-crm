<div class="mb-6 space-y-4">
    <div class="rounded-xl border border-gray-200 px-4 py-3 text-sm text-gray-600 dark:border-white/10 dark:text-gray-300">
        <p class="font-medium text-gray-950 dark:text-white">Flows open inside WhatsApp</p>
        <p class="mt-1">The campus visit flow is built in. Publish it here, then send it from a chat. Your own flows open when someone types the word you choose. A new flow does not replace the visit form.</p>
    </div>

    <div class="rounded-xl border border-gray-200 px-4 py-4 dark:border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="font-medium text-gray-950 dark:text-white">Campus visit</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ ($visitPublished ?? false) ? 'Published. The word is visit.' : 'Not published yet.' }}
                </p>
            </div>
            <button
                type="button"
                wire:click="publishVisit"
                wire:loading.attr="disabled"
                wire:target="publishVisit"
                class="inline-flex items-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white hover:bg-primary-500 disabled:opacity-60"
            >
                <span wire:loading.remove wire:target="publishVisit">{{ ($visitPublished ?? false) ? 'Publish again' : 'Publish' }}</span>
                <span wire:loading wire:target="publishVisit">Publishing…</span>
            </button>
        </div>
    </div>

    <div class="rounded-xl border border-gray-200 dark:border-white/10">
        <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-200 px-4 py-3 dark:border-white/10">
            <p class="font-medium text-gray-950 dark:text-white">Your flows</p>
            <button
                type="button"
                wire:click="startNewFlow"
                class="inline-flex items-center rounded-lg border border-gray-300 px-3 py-2 text-sm font-semibold text-gray-800 hover:bg-gray-50 dark:border-white/10 dark:text-gray-100 dark:hover:bg-white/5"
            >
                New flow
            </button>
        </div>
        @if (($flows ?? []) === [])
            <p class="px-4 py-4 text-sm text-gray-500 dark:text-gray-400">No flow yet. New flow starts a blank one.</p>
        @else
            <ul class="divide-y divide-gray-200 dark:divide-white/10">
                @foreach ($flows as $flow)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-4 py-3">
                        <div>
                            <p class="font-medium text-gray-950 dark:text-white">{{ $flow['name'] }}</p>
                            <p class="mt-0.5 text-sm text-gray-500 dark:text-gray-400">
                                Word: {{ $flow['trigger_word'] }} · {{ $flow['state'] }}
                            </p>
                        </div>
                        <button
                            type="button"
                            wire:click="editFlow({{ (int) $flow['id'] }})"
                            class="text-sm font-semibold text-primary-600 hover:text-primary-500 dark:text-primary-300"
                        >
                            Edit
                        </button>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>

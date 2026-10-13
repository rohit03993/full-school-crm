<div class="mb-6 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-base font-semibold text-gray-950 dark:text-white">Saved forms</h2>
            <p class="text-sm text-gray-500 dark:text-gray-400">Click a form to edit it. New form starts a blank one.</p>
        </div>
        <button
            type="button"
            wire:click="startNewForm"
            class="inline-flex min-h-11 items-center rounded-xl bg-primary-600 px-4 py-2 text-sm font-semibold text-white"
        >
            New form
        </button>
    </div>

    @if ($savedForms === [])
        <p class="text-sm text-gray-500 dark:text-gray-400">No forms yet. Fill the four steps below, then click Publish.</p>
    @else
        <div class="space-y-2">
            @foreach ($savedForms as $row)
                <button
                    type="button"
                    wire:click="editForm({{ $row['id'] }})"
                    class="flex w-full flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 px-4 py-3 text-left text-sm dark:border-white/10"
                >
                    <span class="font-medium text-gray-950 dark:text-white">{{ $row['name'] }}</span>
                    <span class="text-gray-500 dark:text-gray-400">
                        {{ $row['audience'] }} · word {{ $row['trigger_word'] }} · {{ $row['state'] }}
                    </span>
                </button>
            @endforeach
        </div>
    @endif
</div>

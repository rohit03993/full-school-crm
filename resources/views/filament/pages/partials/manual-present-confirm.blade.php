@if ($this->showPresentModal)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center bg-black/40 p-4 sm:items-center"
        wire:click.self="closePresentModal"
    >
        <div
            class="w-full max-w-md rounded-2xl bg-white p-5 shadow-xl dark:bg-gray-900"
            wire:click.stop
        >
            <h3 class="text-base font-bold text-gray-950 dark:text-white">Mark present</h3>
            <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">
                @if ($this->presentBulk)
                    Check in {{ $this->presentBulkCount }} student{{ $this->presentBulkCount === 1 ? '' : 's' }} who have not come yet. Nothing is saved until you confirm.
                @else
                    {{ $this->presentStudentName }} — choose the arrival time. Nothing is saved until you confirm.
                @endif
            </p>

            <label class="mt-4 block text-xs font-semibold uppercase tracking-wide text-gray-500">Arrival time</label>
            <input
                type="time"
                wire:model.live="presentTime"
                class="fi-crm-input mt-1 block w-full"
            />

            <label class="mt-4 flex items-start gap-2 text-sm text-gray-700 dark:text-gray-200">
                <input type="checkbox" wire:model.live="presentNotify" class="mt-0.5 rounded border-gray-300" />
                <span>Send parent message for this time</span>
            </label>

            <div class="mt-5 flex justify-end gap-2">
                <button
                    type="button"
                    wire:click="closePresentModal"
                    class="rounded-lg px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-100 dark:text-gray-200 dark:hover:bg-white/10"
                >
                    Cancel
                </button>
                <button
                    type="button"
                    wire:click="confirmManualIn"
                    wire:loading.attr="disabled"
                    class="rounded-lg bg-emerald-600 px-3 py-2 text-sm font-bold text-white hover:bg-emerald-500 disabled:opacity-60"
                >
                    <span wire:loading.remove wire:target="confirmManualIn">Confirm present</span>
                    <span wire:loading wire:target="confirmManualIn">Saving…</span>
                </button>
            </div>
        </div>
    </div>
@endif

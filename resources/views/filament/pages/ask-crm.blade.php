<x-filament-panels::page>
    <div class="mx-auto max-w-xl rounded-2xl border border-dashed border-gray-300 bg-white px-6 py-10 text-center dark:border-white/15 dark:bg-gray-900">
        <p class="text-base font-semibold text-gray-950 dark:text-white">Ask CRM is always one tap away</p>
        <p class="mt-2 text-sm text-gray-600 dark:text-gray-300">
            On a phone, tap <strong>Ask CRM</strong> in the bottom bar. On a computer, use the chat button at the
            bottom-right of any admin page. Ask about attendance, fees, or homework.
        </p>

        <button
            type="button"
            onclick="window.Livewire && window.Livewire.dispatch('ask-crm-toggle')"
            class="crm-chip crm-chip--active mt-5"
        >
            Open Ask CRM
        </button>
    </div>
</x-filament-panels::page>

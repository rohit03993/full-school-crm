<x-filament-panels::page>
    <form wire:submit="save" class="mx-auto max-w-2xl space-y-4 rounded-xl border border-gray-200 bg-white p-6 dark:border-white/10 dark:bg-gray-900">
        <p class="text-sm text-gray-600 dark:text-gray-300">
            Staff still upload recordings inside this CRM. These settings tell the server where to send the file. Telecallers do not see this page.
        </p>

        <label class="flex items-center gap-2 text-sm font-medium text-gray-900 dark:text-white">
            <input type="checkbox" wire:model="enabled">
            Call AI processing
        </label>

        <label class="block space-y-1 text-sm">
            <span class="font-medium text-gray-900 dark:text-white">Processing address</span>
            <input type="url" wire:model="apiUrl" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950" placeholder="http://127.0.0.1:8001">
        </label>

        <label class="block space-y-1 text-sm">
            <span class="font-medium text-gray-900 dark:text-white">School code</span>
            <input type="text" wire:model="schoolCode" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950">
        </label>

        <label class="block space-y-1 text-sm">
            <span class="font-medium text-gray-900 dark:text-white">School secret</span>
            <input type="password" wire:model="schoolSecret" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950" autocomplete="new-password">
        </label>

        <label class="block space-y-1 text-sm">
            <span class="font-medium text-gray-900 dark:text-white">Callback secret</span>
            <input type="password" wire:model="callbackSecret" class="block w-full rounded-lg border-gray-300 text-sm dark:border-white/10 dark:bg-gray-950" autocomplete="new-password">
        </label>

        <button type="submit" class="inline-flex min-h-11 items-center rounded-lg bg-primary-600 px-4 text-sm font-semibold text-white">
            Save
        </button>
    </form>
</x-filament-panels::page>

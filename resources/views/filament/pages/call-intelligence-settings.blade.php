<x-filament-panels::page>
    @php
        $ready = $enabled && filled($apiUrl) && filled($schoolCode) && filled($schoolSecret) && filled($callbackSecret);
    @endphp

    <form wire:submit="save" class="mx-auto max-w-3xl space-y-4" autocomplete="off">
        <div class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p class="text-sm font-bold text-gray-950 dark:text-white">Connection</p>
                    <p class="mt-1 max-w-xl text-sm text-gray-600 dark:text-gray-300">
                        Staff upload the recording on the student Calls tab. This page only tells the server where to send that file. Telecallers do not see these fields.
                    </p>
                </div>
                @if ($ready)
                    <span class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-bold text-emerald-700 dark:text-emerald-300">Ready</span>
                @else
                    <span class="rounded-full bg-amber-500/15 px-3 py-1 text-xs font-bold text-amber-800 dark:text-amber-200">Not ready</span>
                @endif
            </div>

            <label class="mt-5 flex items-center justify-between gap-4 rounded-xl bg-gray-50 px-4 py-3 dark:bg-white/5">
                <span>
                    <span class="block text-sm font-semibold text-gray-950 dark:text-white">Call AI processing</span>
                    <span class="mt-0.5 block text-xs text-gray-500 dark:text-gray-400">Turn this on only after the address and both secrets are filled.</span>
                </span>
                <input type="checkbox" wire:model.live="enabled" class="h-5 w-5 rounded border-gray-300 text-primary-600 focus:ring-primary-500">
            </label>
        </div>

        <div class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
            <div>
                <p class="text-sm font-bold text-gray-950 dark:text-white">Where the recording goes</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">Use the public address of the Call AI server. On a live school site, <span class="font-semibold">127.0.0.1</span> will not work.</p>
            </div>

            <div>
                <label for="call-ai-url" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Processing address</label>
                <input id="call-ai-url" type="url" wire:model="apiUrl" placeholder="https://calls.example.com" autocomplete="off" class="fi-crm-input">
                @error('apiUrl') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label for="call-ai-code" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">School code</label>
                    <input id="call-ai-code" type="text" wire:model="schoolCode" name="call-ai-school-code" autocomplete="off" autocapitalize="off" spellcheck="false" class="fi-crm-input">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Same code saved on the Call AI server.</p>
                    @error('schoolCode') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="call-ai-school-secret" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">School secret</label>
                    <input id="call-ai-school-secret" type="text" wire:model="schoolSecret" name="call-ai-school-secret" autocomplete="off" autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true" class="fi-crm-input">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Not your staff login password.</p>
                    @error('schoolSecret') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </div>

        <div class="space-y-4 rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 sm:p-6">
            <div>
                <p class="text-sm font-bold text-gray-950 dark:text-white">How the summary comes back</p>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">The Call AI server uses this secret when it sends the transcript back to this school.</p>
            </div>

            <div>
                <label for="call-ai-callback-secret" class="mb-1.5 block text-sm font-medium text-gray-700 dark:text-gray-300">Callback secret</label>
                <input id="call-ai-callback-secret" type="text" wire:model="callbackSecret" name="call-ai-callback-secret" autocomplete="off" autocapitalize="off" spellcheck="false" data-1p-ignore data-lpignore="true" class="fi-crm-input">
                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Must match the callback secret on the Call AI server.</p>
                @error('callbackSecret') <p class="mt-1 text-xs text-danger-600">{{ $message }}</p> @enderror
            </div>

            <button type="submit" class="fi-crm-btn-primary">Save</button>
        </div>
    </form>
</x-filament-panels::page>
